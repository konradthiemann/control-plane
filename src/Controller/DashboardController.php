<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\KnipsAnalyticsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(KnipsAnalyticsClient $knipsAnalyticsClient): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'knipsAnalytics' => $knipsAnalyticsClient->fetchAnalytics(),
        ]);
    }
}
