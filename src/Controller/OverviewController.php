<?php

namespace App\Controller;

use App\Config\AppRegistry;
use App\Repository\GithubIssueRepository;
use App\Service\DashboardChartFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OverviewController extends AbstractController
{
    #[Route('/', name: 'app_overview')]
    public function index(GithubIssueRepository $issueRepository, DashboardChartFactory $charts): Response
    {
        $apps = AppRegistry::all();
        $countsByRepo = $issueRepository->countGroupedByRepo();

        $countsByAppName = [];
        foreach ($apps as $app) {
            $countsByAppName[$app->displayName] = $countsByRepo[$app->githubRepo] ?? 0;
        }

        return $this->render('overview/index.html.twig', [
            'apps' => $apps,
            'totalOpenIssues' => array_sum($countsByAppName),
            'lastSyncedAt' => $issueRepository->findLastSyncedAt(),
            'issuesPerAppChart' => $charts->issuesPerAppChart($countsByAppName),
        ]);
    }
}
