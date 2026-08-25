<?php

namespace App\Tests\Service;

use App\Service\KnipsAnalyticsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KnipsAnalyticsClientTest extends TestCase
{
    public function testFetchAnalyticsAuthenticatesThenReturnsDecodedPayload(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            if (str_ends_with($url, '/api/admin/auth')) {
                return new MockResponse('{}', ['response_headers' => ['set-cookie' => 'fca=session-token; Path=/; HttpOnly']]);
            }

            return new MockResponse(json_encode([
                'byType' => ['app_open' => 42],
                'uploadsByCategory' => [['cat' => 'Der Klassiker', 'count' => 40]],
                'skipsByCategory' => [],
                'devices' => [['device' => 'mobile', 'count' => 38]],
            ]));
        });

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        $result = $client->fetchAnalytics();

        self::assertSame(['app_open' => 42], $result['byType']);
        self::assertCount(2, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://knips.example.test/api/admin/auth', $requests[0]['url']);
        self::assertSame('{"token":"secret-token"}', $requests[0]['options']['body']);
        self::assertSame('GET', $requests[1]['method']);
        self::assertContains('Cookie: fca=session-token', $requests[1]['options']['headers']);
    }

    public function testFetchAnalyticsReturnsNullWhenKnipsIsUnreachable(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('', ['error' => 'connection refused']));

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        self::assertNull($client->fetchAnalytics());
    }

    public function testFetchStatsAuthenticatesThenReturnsDecodedPayload(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_ends_with($url, '/api/admin/auth')) {
                return new MockResponse('{}', ['response_headers' => ['set-cookie' => 'fca=session-token; Path=/; HttpOnly']]);
            }

            return new MockResponse(json_encode([
                'totals' => ['events' => 1, 'guests' => 30, 'photos' => 67, 'revenueCents' => 0],
                'tierCounts' => ['3' => 0, '5' => 1],
                'days' => [['date' => '2026-08-22', 'events' => 1, 'guests' => 30, 'photos' => 67]],
                'events' => [['id' => 'party', 'name' => 'Annette & Björn', 'guestLimit' => 5, 'guestCount' => 30, 'photoCount' => 67, 'priceCents' => 99, 'active' => true]],
            ]));
        });

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        $result = $client->fetchStats();

        self::assertSame(1, $result['totals']['events']);
        self::assertSame(67, $result['totals']['photos']);
        self::assertCount(1, $result['days']);
        self::assertSame('Annette & Björn', $result['events'][0]['name']);
    }

    public function testFetchStorageReturnsDecodedPayload(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_ends_with($url, '/api/admin/auth')) {
                return new MockResponse('{}', ['response_headers' => ['set-cookie' => 'fca=session-token; Path=/; HttpOnly']]);
            }

            return new MockResponse(json_encode(['fileCount' => 73, 'totalBytes' => 43338732]));
        });

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        self::assertSame(['fileCount' => 73, 'totalBytes' => 43338732], $client->fetchStorage());
    }

    public function testDeleteEventReturnsTrueOnSuccess(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url];

            if (str_ends_with($url, '/api/admin/auth')) {
                return new MockResponse('{}', ['response_headers' => ['set-cookie' => 'fca=session-token; Path=/; HttpOnly']]);
            }

            return new MockResponse('{"ok":true}');
        });

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        self::assertTrue($client->deleteEvent('party'));
        self::assertSame('DELETE', $requests[1]['method']);
        self::assertSame('https://knips.example.test/api/admin/events/party', $requests[1]['url']);
    }

    public function testDeleteEventReturnsFalseWhenEventNotFound(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_ends_with($url, '/api/admin/auth')) {
                return new MockResponse('{}', ['response_headers' => ['set-cookie' => 'fca=session-token; Path=/; HttpOnly']]);
            }

            return new MockResponse('{"error":"not_found"}', ['http_code' => 404]);
        });

        $client = new KnipsAnalyticsClient($httpClient, 'https://knips.example.test', 'secret-token');

        self::assertFalse($client->deleteEvent('does-not-exist'));
    }
}
