<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads Doewe's cross-household aggregate stats. Simpler auth than
 * KnipsAnalyticsClient — a single Bearer token header, no login handshake.
 */
class DoeweAnalyticsClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'DOEWE_BASE_URL')] private readonly string $baseUrl,
        #[Autowire(env: 'DOEWE_SERVICE_TOKEN')] private readonly string $serviceToken,
    ) {
    }

    /**
     * @return array<string, mixed>|null null if Doewe is unreachable or auth fails
     */
    public function fetchStats(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/stats', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
