<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReportController
{
    #[Route('/reports', name: 'reports')]
    public function index(): Response
    {
        return new Response('reports');
    }

    #[IsGranted('ROLE_ANALYST')]
    public function list(): Response
    {
        return new Response('list');
    }
}
