<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Phil\AccessBundle\Infrastructure\Http\Attribute\AllowedDuringViewAs;
use Phil\AccessBundle\Infrastructure\Http\Attribute\RefusedDuringViewAs;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller with an action of each kind for the guard of "view as this member".
 */
final class GuardedController
{
    public function plain(): Response
    {
        return new Response();
    }

    #[AllowedDuringViewAs]
    public function allowed(): Response
    {
        return new Response();
    }

    #[RefusedDuringViewAs]
    public function refused(): Response
    {
        return new Response();
    }
}
