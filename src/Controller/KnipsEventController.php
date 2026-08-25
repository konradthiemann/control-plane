<?php

namespace App\Controller;

use App\Service\KnipsAnalyticsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Knips-specific event moderation (price override, pause). Classic
 * form + redirect rather than a Live Component action — an editable field
 * per table row would need per-row two-way bindings, more complexity than
 * these rarely-used actions justify.
 */
class KnipsEventController extends AbstractController
{
    #[Route('/apps/knips/events/{eventId}/price', name: 'app_knips_event_price', methods: ['POST'])]
    public function updatePrice(string $eventId, Request $request, KnipsAnalyticsClient $client): RedirectResponse
    {
        if ('reset' === $request->request->get('action')) {
            $client->updateEvent($eventId, ['priceOverrideCents' => null]);
        } else {
            $euros = str_replace(',', '.', (string) $request->request->get('priceEuros', ''));
            if (is_numeric($euros) && (float) $euros >= 0) {
                $cents = (int) round(((float) $euros) * 100);
                $client->updateEvent($eventId, ['priceOverrideCents' => $cents]);
            }
        }

        return $this->redirectToRoute('app_show', ['slug' => 'knips']);
    }

    #[Route('/apps/knips/events/{eventId}/moderation', name: 'app_knips_event_moderation', methods: ['POST'])]
    public function updateModeration(string $eventId, Request $request, KnipsAnalyticsClient $client): RedirectResponse
    {
        $suspended = $request->request->getBoolean('suspended');
        $client->updateEvent($eventId, ['suspended' => $suspended]);

        return $this->redirectToRoute('app_show', ['slug' => 'knips']);
    }
}
