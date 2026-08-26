<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads Doewe's cross-household aggregate stats and, since the CRM/moderation
 * feature, also the admin user/household lists and their moderation actions.
 * Simpler auth than KnipsAnalyticsClient — a single Bearer token header, no
 * login handshake.
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

    /**
     * @return array{page: int, pageSize: int, total: int, users: list<array<string, mixed>>}|null
     *         null if Doewe is unreachable or auth fails
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
     * @return array{households: list<array<string, mixed>>}|null null if Doewe is unreachable or auth fails
     */
    public function fetchHouseholds(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/api/admin/households', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array{days: int, from: string, to: string, series: list<array{date: string, logins: int, transactions: int, receiptScans: int}>}|null
     *         null if Doewe is unreachable or auth fails
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
     * Soft-delete only — Doewe never hard-deletes a user record. Idempotent
     * on Doewe's side, so calling this on an already-deleted user still
     * returns true.
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

    /**
     * Splits a household member out into a fresh, empty solo household they
     * own. Historical accounts/transactions stay with the old household —
     * see Doewe's `split-member` route for the underlying product decision.
     */
    public function splitHouseholdMember(string $householdId, string $userId): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/households/'.rawurlencode($householdId).'/split-member', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
                'json' => ['userId' => $userId],
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * Soft-delete only — cascades to every current member on Doewe's side
     * (locks out the whole household, not just hides it). Idempotent.
     */
    public function deleteHousehold(string $householdId): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/api/admin/households/'.rawurlencode($householdId).'/delete', [
                'headers' => ['Authorization' => 'Bearer '.$this->serviceToken],
            ]);

            return 200 === $response->getStatusCode();
        } catch (ExceptionInterface) {
            return false;
        }
    }
}
