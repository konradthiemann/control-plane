<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the aggregated usage analytics Knips (Foto-Challenge) exposes for
 * this backend. See Foto-Challenge/docs/analytics-api.md for the contract.
 */
class KnipsAnalyticsClient
{
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

    private function authenticate(): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/auth', [
            'json' => ['token' => $this->adminToken],
        ]);

        $setCookie = $response->getHeaders()['set-cookie'][0] ?? null;
        if (null === $setCookie) {
            throw new TransportException('Knips auth response did not set a session cookie.');
        }

        return explode(';', $setCookie, 2)[0];
    }
}
