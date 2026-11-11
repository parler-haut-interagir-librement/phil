<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller;

use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return new Response('dashboard');
    }

    #[Route('/admin/help', name: 'admin_help', methods: ['GET'])]
    #[PublicRoute(reason: 'Help is for everybody.')]
    public function help(): Response
    {
        return new Response('help');
    }
}
