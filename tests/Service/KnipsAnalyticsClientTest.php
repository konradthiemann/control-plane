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
}
