<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Contract;

use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Symfony\Component\HttpFoundation\Request;

/**
 * A rate limiter the attribute, the route default and the form option name: the bundle's own, over
 * `symfony/rate-limiter`, or another package's or the application's, registered as a service tagged
 * `phil_rate_limit.limiter` with its `name`.
 *
 * An implementation keeps no state of its own between requests: what it counts lives in its storage.
 */
interface Limiter
{
    /**
     * Consumes `$amount` for `$key` and says whether the request may go on.
     *
     * @param string  $key     1 to 200 bytes, never a raw address nor an identifier in clear
     * @param int     $amount  one or more
     * @param Request $request the request consumed for, for a limiter that reads more of it
     */
    public function consume(string $key, int $amount, Request $request): Decision;
}
