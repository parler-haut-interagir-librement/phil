<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller;

use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController
{
    #[Route('/login', name: 'login')]
    #[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
    public function login(): Response
    {
        return new Response('login');
    }

    #[Route('/api/items', name: 'api_items')]
    public function items(): Response
    {
        return new Response('items');
    }
}
