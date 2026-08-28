<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads Prized's admin user list/stats and moderates accounts. Same
 * Bearer-token pattern as DoeweAnalyticsClient — see
 * Pok-mon-TCG-Prize-Checker/specs/admin-api.md for the contract.
 *
 * Unlike Doewe, deleteUser() is a real hard delete on Prized's side (no
 * shared household data that a delete could break for someone else), and
 * suspendUser()/deleteUser() are idempotent by contract — calling them again
 * on an already-suspended/deleted account still succeeds.
 */
class PrizedAnalyticsClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'PRIZED_BASE_URL')] private readonly string $baseUrl,
        #[Autowire(env: 'PRIZED_SERVICE_TOKEN')] private readonly string $serviceToken,
    ) {
    }

    /**
     * @return array<string, mixed>|null null if Prized is unreachable or auth fails
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

    /**
     * @return array{page: int, pageSize: int, total: int, users: list<array<string, mixed>>}|null
     *         null if Prized is unreachable or auth fails
     */
    public function fetchUsers(int $page = 1, int $pageSize = 200): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/users', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
                'query' => ['page' => $page, 'pageSize' => $pageSize],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return list<array{date: string, roundsPlayed: int, avgDurationSec: float, avgAccuracy: float, newUsers: int, activeUsers: int}>|null
     *         null if Prized is unreachable or auth fails
     */
    public function fetchUsage(int $days = 30): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/usage', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
                'query' => ['days' => $days],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return list<array{label: string, roundCount: int, avgAccuracy: float}>|null
     *         null if Prized is unreachable or auth fails
     */
    public function fetchDecksSummary(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/decks-summary', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    public function suspendUser(string $userId, bool $suspended): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/users/'.rawurlencode($userId).'/suspend', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
                'json' => ['suspended' => $suspended],
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * Hard-delete — Prized hat keine geteilten Haushalts-/Mehrbenutzer-Daten,
     * die dadurch für andere kaputtgehen könnten. Idempotent auf Prizeds
     * Seite: ein bereits gelöschter Account gibt weiterhin `true` zurück.
     */
    public function deleteUser(string $userId): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/users/'.rawurlencode($userId).'/delete', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }
}
