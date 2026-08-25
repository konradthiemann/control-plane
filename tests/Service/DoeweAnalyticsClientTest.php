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
}
