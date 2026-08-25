<?php

namespace App\Tests\Service;

use App\Service\GithubIssuesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GithubIssuesClientTest extends TestCase
{
    public function testFetchOpenIssuesFiltersPullRequestsAndAggregatesAcrossRepos(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            self::assertSame('GET', $method);

            if (str_contains($url, '/repos/konradthiemann/Doewe/issues')) {
                return new MockResponse(json_encode([
                    ['number' => 12, 'title' => 'Bug im Steuerexport', 'html_url' => 'https://github.com/konradthiemann/Doewe/issues/12', 'created_at' => '2026-08-01T10:00:00Z', 'updated_at' => '2026-08-02T10:00:00Z'],
                    ['number' => 13, 'title' => 'A pull request', 'html_url' => 'https://github.com/konradthiemann/Doewe/pull/13', 'created_at' => '2026-08-01T10:00:00Z', 'updated_at' => '2026-08-02T10:00:00Z', 'pull_request' => ['url' => '...']],
                ]));
            }

            if (str_contains($url, '/repos/konradthiemann/Pokekon/issues')) {
                return new MockResponse(json_encode([
                    ['number' => 4, 'title' => 'Deck-Import schlägt fehl', 'html_url' => 'https://github.com/konradthiemann/Pokekon/issues/4', 'created_at' => '2026-08-03T10:00:00Z', 'updated_at' => '2026-08-03T10:00:00Z'],
                ]));
            }

            return new MockResponse('[]');
        });

        $client = new GithubIssuesClient($httpClient, 'test-token', 'konradthiemann/Doewe,konradthiemann/Pokekon');

        $issues = $client->fetchOpenIssues();

        self::assertCount(2, $issues);
        self::assertSame('Doewe', $issues[0]['repo']);
        self::assertSame(12, $issues[0]['number']);
        self::assertSame('Pokekon', $issues[1]['repo']);
        self::assertSame(4, $issues[1]['number']);
    }

    public function testFetchOpenIssuesSkipsUnreachableRepoWithoutBlockingOthers(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, '/repos/konradthiemann/Broken/issues')) {
                return new MockResponse('', ['error' => 'connection refused']);
            }

            return new MockResponse(json_encode([
                ['number' => 1, 'title' => 'Läuft', 'html_url' => 'https://github.com/konradthiemann/Ok/issues/1', 'created_at' => '2026-08-01T10:00:00Z', 'updated_at' => '2026-08-01T10:00:00Z'],
            ]));
        });

        $client = new GithubIssuesClient($httpClient, 'test-token', 'konradthiemann/Broken,konradthiemann/Ok');

        $issues = $client->fetchOpenIssues();

        self::assertCount(1, $issues);
        self::assertSame('Ok', $issues[0]['repo']);
    }
}
