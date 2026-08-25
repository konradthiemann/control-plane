<?php

namespace App\Twig\Components;

use App\Service\KnipsAnalyticsClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Self-contained (like IssueBoard): fetches Knips stats itself on every
 * render so a delete is reflected immediately, without a shared cache.
 */
#[AsLiveComponent]
class KnipsEventBoard
{
    use DefaultActionTrait;

    private ?string $lastError = null;

    public function __construct(private readonly KnipsAnalyticsClient $client)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEvents(): array
    {
        $stats = $this->client->fetchStats();

        return $stats['events'] ?? [];
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    #[LiveAction]
    public function deleteEvent(#[LiveArg] string $eventId): void
    {
        if (!$this->client->deleteEvent($eventId)) {
            $this->lastError = 'Löschen fehlgeschlagen — Knips war nicht erreichbar oder das Event existiert nicht mehr.';
        }
    }
}
