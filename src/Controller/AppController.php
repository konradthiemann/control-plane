<?php

namespace App\Controller;

use App\Config\AppRegistry;
use App\Service\DashboardChartFactory;
use App\Service\DoeweAnalyticsClient;
use App\Service\KnipsAnalyticsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/apps/{slug}', name: 'app_show')]
    public function show(
        string $slug,
        KnipsAnalyticsClient $knipsAnalyticsClient,
        DoeweAnalyticsClient $doeweAnalyticsClient,
        DashboardChartFactory $charts,
    ): Response {
        $appEntry = AppRegistry::findBySlug($slug);
        if (null === $appEntry) {
            throw $this->createNotFoundException("Unbekannte App \"{$slug}\".");
        }

        $knipsAnalytics = null;
        $knipsCharts = null;
        $knipsStats = null;
        $knipsStorage = null;
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

            $knipsStats = $knipsAnalyticsClient->fetchStats();
            if (null !== $knipsStats) {
                $days = $knipsStats['days'] ?? [];
                $knipsCharts['photosPerDay'] = $charts->knipsDailySeriesChart($days, 'photos', 'Fotos/Tag');
                $knipsCharts['guestsPerDay'] = $charts->knipsDailySeriesChart($days, 'guests', 'Gäste/Tag');
                $knipsCharts['eventsPerDay'] = $charts->knipsDailySeriesChart($days, 'events', 'Events/Tag');
                $knipsCharts['tiers'] = $charts->knipsTierChart($knipsStats['tierCounts'] ?? []);
                $knipsCharts['photosByHour'] = $charts->knipsPhotosByHourChart($knipsStats['photosByHour'] ?? []);
            }

            $knipsStorage = $knipsAnalyticsClient->fetchStorage();
        }

        $doeweStats = null;
        $doeweChart = null;
        if ($appEntry->hasDoeweAnalytics) {
            $doeweStats = $doeweAnalyticsClient->fetchStats();
            if (null !== $doeweStats) {
                $doeweChart = $charts->doeweDataQualityChart($doeweStats['transactions']);
            }
        }

        $doeweUsage = null;
        $doeweUsageCharts = null;
        if ($appEntry->hasDoeweCrm) {
            $doeweUsage = $doeweAnalyticsClient->fetchUsage(30);
            if (null !== $doeweUsage) {
                $series = $doeweUsage['series'] ?? [];
                $doeweUsageCharts = [
                    'logins' => $charts->doeweUsageChart($series, 'logins', 'Logins/Tag'),
                    'transactions' => $charts->doeweUsageChart($series, 'transactions', 'Transaktionen/Tag'),
                    'receiptScans' => $charts->doeweUsageChart($series, 'receiptScans', 'Beleg-Scans/Tag'),
                ];
            }
        }

        return $this->render('app/show.html.twig', [
            'apps' => AppRegistry::all(),
            'appEntry' => $appEntry,
            'knipsAnalytics' => $knipsAnalytics,
            'knipsStats' => $knipsStats,
            'knipsStorage' => $knipsStorage,
            'knipsCharts' => $knipsCharts,
            'doeweStats' => $doeweStats,
            'doeweChart' => $doeweChart,
            'doeweUsage' => $doeweUsage,
            'doeweUsageCharts' => $doeweUsageCharts,
        ]);
    }
}
