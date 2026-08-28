<?php

namespace App\Twig\Components;

use App\Service\PrizedAnalyticsClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Self-contained like DoeweCrmBoard: fetches the account list itself on
 * every render so a moderation action is reflected immediately.
 *
 * v1 scope: single large page (pageSize 200, no pagination controls) — same
 * reasoning as DoeweCrmBoard, small user count for now.
 */
#[AsLiveComponent]
class PrizedCrmBoard
{
    use DefaultActionTrait;

    private ?string $lastError = null;

    public function __construct(private readonly PrizedAnalyticsClient $client)
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

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    #[LiveAction]
    public function suspendUser(#[LiveArg] string $userId, #[LiveArg] bool $suspended): void
    {
        if (!$this->client->suspendUser($userId, $suspended)) {
            $this->lastError = $suspended
                ? 'Sperren fehlgeschlagen — Prized war nicht erreichbar oder der Account existiert nicht mehr.'
                : 'Entsperren fehlgeschlagen — Prized war nicht erreichbar oder der Account existiert nicht mehr.';
        }
    }

    #[LiveAction]
    public function deleteUser(#[LiveArg] string $userId): void
    {
        if (!$this->client->deleteUser($userId)) {
            $this->lastError = 'Löschen fehlgeschlagen — Prized war nicht erreichbar.';
        }
    }
}
