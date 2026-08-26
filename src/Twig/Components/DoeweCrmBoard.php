<?php

namespace App\Twig\Components;

use App\Service\DoeweAnalyticsClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Self-contained (like KnipsEventBoard/IssueBoard): fetches the user and
 * household lists itself on every render so a moderation action is reflected
 * immediately, without a shared cache.
 *
 * No explicit CSRF token is used on the LiveActions below — symfony/ux-live-
 * component 3.4 dropped CSRF tokens in favor of enforcing same-origin
 * requests via the mandatory `X-Requested-With` header (see its CHANGELOG),
 * which already protects every LiveAction in this app. This is not an
 * oversight.
 *
 * v1 scope: the users list is fetched with a single large page (pageSize:
 * 200, no pagination controls) — Doewe's real user count is small right now
 * (test/feedback users, pre-public-launch). Revisit with real pagination
 * controls once user count grows past that.
 */
#[AsLiveComponent]
class DoeweCrmBoard
{
    use DefaultActionTrait;

    private ?string $lastError = null;

    public function __construct(private readonly DoeweAnalyticsClient $client)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUsers(): array
    {
        $result = $this->client->fetchUsers(1, 200);

        return $result['users'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHouseholds(): array
    {
        $result = $this->client->fetchHouseholds();

        return $result['households'] ?? [];
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    #[LiveAction]
    public function suspendUser(#[LiveArg] string $userId, #[LiveArg] bool $suspended): void
    {
        if (!$this->client->suspendUser($userId, $suspended)) {
            $this->lastError = $suspended
                ? 'Sperren fehlgeschlagen — Doewe war nicht erreichbar oder der Nutzer existiert nicht mehr.'
                : 'Entsperren fehlgeschlagen — Doewe war nicht erreichbar oder der Nutzer existiert nicht mehr.';
        }
    }

    #[LiveAction]
    public function deleteUser(#[LiveArg] string $userId): void
    {
        if (!$this->client->deleteUser($userId)) {
            $this->lastError = 'Löschen fehlgeschlagen — Doewe war nicht erreichbar oder der Nutzer existiert nicht mehr.';
        }
    }

    #[LiveAction]
    public function splitMember(#[LiveArg] string $householdId, #[LiveArg] string $userId): void
    {
        if (!$this->client->splitHouseholdMember($householdId, $userId)) {
            $this->lastError = 'Abspalten fehlgeschlagen — Doewe war nicht erreichbar oder das Mitglied gehört nicht mehr zu diesem Haushalt.';
        }
    }
}
