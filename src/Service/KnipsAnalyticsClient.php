<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the aggregated usage analytics Knips (Foto-Challenge) exposes for
 * this backend. See Foto-Challenge/docs/analytics-api.md for the contract.
 *
 * fetchAnalytics()'s payload may additionally contain a `taskStats` list
 * (per-task play/skip/abandon counts and rates) and a top-level
 * `taskStatsMinExposures` (minimum sample size below which a task's rates
 * aren't statistically meaningful) — both are consumed by KnipsTaskRanking.
 * Older Knips deployments may not send these fields yet; treat their
 * absence as "no data", not an error.
 */
class KnipsAnalyticsClient
{
    /**
     * Cached for the lifetime of this service instance (= one HTTP request,
     * since it's a shared/singleton service) so that a page load calling
     * fetchAnalytics()/fetchStats()/fetchStorage()/deleteEvent() multiple
     * times only authenticates once. Without this, a handful of clicks
     * (each triggering an authenticate + a Live Component re-render, itself
     * re-authenticating) can trip Knips' own rate limit on /api/admin/auth
     * (20 requests / 5 min, see Foto-Challenge/src/server.js authLimiter).
     */
    private ?string $cachedSessionCookie = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'KNIPS_BASE_URL')] private readonly string $baseUrl,
        #[Autowire(env: 'KNIPS_ADMIN_TOKEN')] private readonly string $adminToken,
    ) {
    }

    /**
     * @return array<string, mixed>|null null if Knips is unreachable or auth fails
     */
    public function fetchAnalytics(): ?array
    {
        try {
            $sessionCookie = $this->authenticate();
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/analytics', [
                'headers' => ['Cookie' => $sessionCookie],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null null if Knips is unreachable or auth fails
     */
    public function fetchStats(): ?array
    {
        try {
            $sessionCookie = $this->authenticate();
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/stats', [
                'headers' => ['Cookie' => $sessionCookie],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array{fileCount: int, totalBytes: int}|null null if Knips is unreachable or auth fails
     */
    public function fetchStorage(): ?array
    {
        try {
            $sessionCookie = $this->authenticate();
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/storage', [
                'headers' => ['Cookie' => $sessionCookie],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    public function deleteEvent(string $eventId): bool
    {
        try {
            $sessionCookie = $this->authenticate();
            $response = $this->httpClient->request('DELETE', $this->baseUrl.'/api/admin/events/'.rawurlencode($eventId), [
                'headers' => ['Cookie' => $sessionCookie],
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * @param array{priceOverrideCents?: int|null, suspended?: bool} $changes
     */
    public function updateEvent(string $eventId, array $changes): bool
    {
        try {
            $sessionCookie = $this->authenticate();
            $response = $this->httpClient->request('PATCH', $this->baseUrl.'/api/admin/events/'.rawurlencode($eventId), [
                'headers' => ['Cookie' => $sessionCookie],
                'json' => $changes,
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }

    private function authenticate(): string
    {
        if (null !== $this->cachedSessionCookie) {
            return $this->cachedSessionCookie;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/auth', [
            'json' => ['token' => $this->adminToken],
        ]);

        $setCookie = $response->getHeaders()['set-cookie'][0] ?? null;
        if (null === $setCookie) {
            throw new TransportException('Knips auth response did not set a session cookie.');
        }

        return $this->cachedSessionCookie = explode(';', $setCookie, 2)[0];
    }
}
