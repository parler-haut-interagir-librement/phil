<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Phil\EnumsBundle\Infrastructure\Http\ParseEnum;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;

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

    /**
     * Cached while the status is an open one: an expression of a controller attribute that calls the bundle's
     * functions, which `lint:expressions` checks.
     */
    #[Cache(maxage: 60, if: "enum_in_group(enum_from(request.attributes.get('status'), 'Phil\\\\EnumsBundle\\\\Tests\\\\Functional\\\\App\\\\src\\\\Enum\\\\ParcelStatus'), 'open')")]
    public function cached(string $status): Response
    {
        return new Response('cached: '.$status);
    }
}
