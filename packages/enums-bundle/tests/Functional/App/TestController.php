<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Phil\EnumsBundle\Infrastructure\Http\ParseEnum;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controllers that read a status from the route, leniently.
 */
final class TestController
{
    public function status(#[ParseEnum(ignoreCase: true)] ParcelStatus $status): Response
    {
        return new Response($status->value);
    }

    public function open(string $status): Response
    {
        return new Response('open: '.$status);
    }
}
