<?php

namespace App\Controller;

use App\Config\AppRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IssueController extends AbstractController
{
    #[Route('/issues', name: 'app_issues')]
    public function index(): Response
    {
        return $this->render('issue/index.html.twig', [
            'apps' => AppRegistry::all(),
        ]);
    }
}
