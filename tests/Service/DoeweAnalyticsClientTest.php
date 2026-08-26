<?php

namespace App\Tests\Service;

use App\Service\DoeweAnalyticsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class DoeweAnalyticsClientTest extends TestCase
{
    public function testFetchStatsSendsBearerTokenAndReturnsDecodedPayload(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'households' => ['total' => 6, 'multiMember' => 0],
                'users' => ['total' => 6, 'pushEnabled' => 0],
                'accounts' => ['total' => 6],
                'transactions' => ['total' => 2359, 'categorized' => 2358, 'taxRelevant' => 4, 'fromReceiptScan' => 4],
                'attachments' => ['count' => 3, 'totalBytes' => 658187],
                'recurringTransactions' => ['active' => 37],
                'budgets' => ['total' => 39, 'completed' => 5],
            ]));
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-service-token');

        $result = $client->fetchStats();

        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://doewe.example.test/api/admin/stats', $requests[0]['url']);
        self::assertContains('Authorization: Bearer secret-service-token', $requests[0]['options']['headers']);
        self::assertSame(6, $result['households']['total']);
        self::assertSame(2359, $result['transactions']['total']);
    }

    public function testFetchStatsReturnsNullWhenUnauthorized(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"Unauthorized"}', ['http_code' => 401]));

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'wrong-token');

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
                'total' => 2,
                'users' => [
                    ['id' => 'u1', 'email' => 'a@example.test', 'name' => 'A', 'createdAt' => '2026-01-01T00:00:00Z', 'householdId' => 'h1', 'suspendedAt' => null, 'deletedAt' => null, 'lastLoginAt' => null],
                    ['id' => 'u2', 'email' => 'b@example.test', 'name' => 'B', 'createdAt' => '2026-01-02T00:00:00Z', 'householdId' => 'h1', 'suspendedAt' => null, 'deletedAt' => null, 'lastLoginAt' => '2026-08-20T10:00:00Z'],
                ],
            ]));
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        $result = $client->fetchUsers(1, 200);

        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://doewe.example.test/api/admin/users?page=1&pageSize=200', $requests[0]['url']);
        self::assertSame(2, $result['total']);
        self::assertCount(2, $result['users']);
    }

    public function testFetchHouseholdsReturnsDecodedPayload(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(json_encode([
            'households' => [
                ['id' => 'h1', 'name' => 'Familie A', 'memberCount' => 2, 'createdAt' => '2026-01-01T00:00:00Z', 'accountsCount' => 3, 'transactionsCount' => 40, 'receiptScanCount' => 5],
            ],
        ])));

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        $result = $client->fetchHouseholds();

        self::assertCount(1, $result['households']);
        self::assertSame(5, $result['households'][0]['receiptScanCount']);
    }

    public function testFetchUsageSendsDaysQueryAndReturnsSeries(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'days' => 2,
                'from' => '2026-08-24T00:00:00.000Z',
                'to' => '2026-08-26T00:00:00.000Z',
                'series' => [
                    ['date' => '2026-08-24', 'logins' => 3, 'transactions' => 10, 'receiptScans' => 1],
                    ['date' => '2026-08-25', 'logins' => 5, 'transactions' => 8, 'receiptScans' => 2],
                ],
            ]));
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        $result = $client->fetchUsage(2);

        self::assertSame('https://doewe.example.test/api/admin/usage?days=2', $requests[0]['url']);
        self::assertCount(2, $result['series']);
        self::assertSame(3, $result['series'][0]['logins']);
    }

    public function testSuspendUserSendsSuspendedFlagAsJsonBody(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"suspendedAt":"2026-08-26T00:00:00.000Z"}');
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        self::assertTrue($client->suspendUser('u1', true));
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://doewe.example.test/api/admin/users/u1/suspend', $requests[0]['url']);
        self::assertSame('{"suspended":true}', $requests[0]['options']['body']);
    }

    public function testSuspendUserReturnsFalseWhenUserNotFound(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"User not found"}', ['http_code' => 404]));

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        self::assertFalse($client->suspendUser('unknown', true));
    }

    public function testDeleteUserSendsNoBodyAndReturnsTrueOnSuccess(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"deletedAt":"2026-08-26T00:00:00.000Z"}');
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        self::assertTrue($client->deleteUser('u1'));
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://doewe.example.test/api/admin/users/u1/delete', $requests[0]['url']);
    }

    public function testSplitHouseholdMemberSendsUserIdAsJsonBody(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"newHouseholdId":"h2"}');
        });

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        self::assertTrue($client->splitHouseholdMember('h1', 'u2'));
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://doewe.example.test/api/admin/households/h1/split-member', $requests[0]['url']);
        self::assertSame('{"userId":"u2"}', $requests[0]['options']['body']);
    }

    public function testSplitHouseholdMemberReturnsFalseWhenMemberNotInHousehold(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{"error":"Member not found in household"}', ['http_code' => 404]));

        $client = new DoeweAnalyticsClient($httpClient, 'https://doewe.example.test', 'secret-token');

        self::assertFalse($client->splitHouseholdMember('h1', 'unknown'));
    }
}
