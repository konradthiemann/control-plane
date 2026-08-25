<?php

namespace App\Controller;

use App\Config\AppRegistry;
use App\Service\DashboardChartFactory;
use App\Service\KnipsAnalyticsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/apps/{slug}', name: 'app_show')]
    public function show(string $slug, KnipsAnalyticsClient $knipsAnalyticsClient, DashboardChartFactory $charts): Response
    {
        $appEntry = AppRegistry::findBySlug($slug);
        if (null === $appEntry) {
            throw $this->createNotFoundException("Unbekannte App \"{$slug}\".");
        }

        $knipsAnalytics = null;
        $knipsCharts = null;
        if ($appEntry->hasKnipsAnalytics) {
            $knipsAnalytics = $knipsAnalyticsClient->fetchAnalytics();
            if (null !== $knipsAnalytics) {
                $knipsCharts = [
                    'funnel' => $charts->knipsFunnelChart($knipsAnalytics['funnel'] ?? []),
                    'uploads' => $charts->knipsCategoryChart($knipsAnalytics['uploadsByCategory'] ?? [], 'Uploads'),
                    'skips' => $charts->knipsCategoryChart($knipsAnalytics['skipsByCategory'] ?? [], 'Übersprungen'),
                    'devices' => $charts->knipsDevicesChart($knipsAnalytics['devices'] ?? []),
                ];
            }
        }

        return $this->render('app/show.html.twig', [
            'apps' => AppRegistry::all(),
            'appEntry' => $appEntry,
            'knipsAnalytics' => $knipsAnalytics,
            'knipsCharts' => $knipsCharts,
        ]);
    }
}
