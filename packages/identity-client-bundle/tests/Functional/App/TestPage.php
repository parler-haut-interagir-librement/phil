<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;

final class TestPage
{
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}
