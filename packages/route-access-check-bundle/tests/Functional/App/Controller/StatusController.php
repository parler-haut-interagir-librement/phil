<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class StatusController
{
    #[IsGranted('ROLE_OPS')]
    public function __invoke(): Response
    {
        return new Response('status');
    }
}
