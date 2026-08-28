<?php

namespace App\Tests\Service;

use App\Service\PrizedAnalyticsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PrizedAnalyticsClientTest extends TestCase
{
    public function testFetchStatsSendsBearerTokenAndReturnsDecodedPayload(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'totalUsers' => 12,
                'totalDecks' => 30,
                'totalRounds' => 214,
                'newUsersLast7d' => 2,
                'newUsersLast30d' => 5,
            ]));
        });

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-service-token');

        $result = $client->fetchStats();

        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://prized.example.test/api/admin/stats', $requests[0]['url']);
        self::assertContains('Authorization: Bearer secret-service-token', $requests[0]['options']['headers']);
        self::assertSame(12, $result['totalUsers']);
    }

    public function testFetchStatsReturnsNullWhenUnauthorized(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"Unauthorized"}', ['http_code' => 401]));

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'wrong-token');

        self::assertNull($client->fetchStats());
    }

    public function testFetchUsersSendsPaginationQueryAndReturnsDecodedPayload(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'page' => 1,
                'pageSize' => 200,
                'total' => 1,
                'users' => [
                    ['id' => 'u1', 'email' => 'a@example.test', 'createdAt' => '2026-01-01T00:00:00Z', 'lastSignInAt' => null, 'banned' => false, 'deckCount' => 2, 'roundCount' => 5, 'lastRoundAt' => null],
                ],
            ]));
        });

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        $result = $client->fetchUsers(1, 200);

        self::assertSame('https://prized.example.test/api/admin/users?page=1&pageSize=200', $requests[0]['url']);
        self::assertSame(1, $result['total']);
        self::assertCount(1, $result['users']);
    }

    public function testFetchUsageSendsDaysQueryAndReturnsSeries(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                ['date' => '2026-08-24', 'roundsPlayed' => 10, 'avgDurationSec' => 45.5, 'avgAccuracy' => 0.8, 'newUsers' => 1, 'activeUsers' => 4],
            ]));
        });

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        $result = $client->fetchUsage(30);

        self::assertSame('https://prized.example.test/api/admin/usage?days=30', $requests[0]['url']);
        self::assertCount(1, $result);
        self::assertSame(10, $result[0]['roundsPlayed']);
    }

    public function testFetchDecksSummaryReturnsDecodedPayload(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(json_encode([
            ['label' => 'Dragapult', 'roundCount' => 40, 'avgAccuracy' => 0.76],
        ])));

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        $result = $client->fetchDecksSummary();

        self::assertCount(1, $result);
        self::assertSame('Dragapult', $result[0]['label']);
    }

    public function testSuspendUserSendsSuspendedFlagAsJsonBody(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true}');
        });

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        self::assertTrue($client->suspendUser('u1', true));
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://prized.example.test/api/admin/users/u1/suspend', $requests[0]['url']);
        self::assertSame('{"suspended":true}', $requests[0]['options']['body']);
    }

    public function testSuspendUserReturnsFalseWhenUserNotFound(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"not found"}', ['http_code' => 404]));

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        self::assertFalse($client->suspendUser('unknown', true));
    }

    public function testDeleteUserSendsNoBodyAndReturnsTrueOnSuccess(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true}');
        });

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        self::assertTrue($client->deleteUser('u1'));
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://prized.example.test/api/admin/users/u1/delete', $requests[0]['url']);
    }

    public function testDeleteUserReturnsFalseOnServerError(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"upstream"}', ['http_code' => 502]));

        $client = new PrizedAnalyticsClient($httpClient, 'https://prized.example.test', 'secret-token');

        self::assertFalse($client->deleteUser('u1'));
    }
}
