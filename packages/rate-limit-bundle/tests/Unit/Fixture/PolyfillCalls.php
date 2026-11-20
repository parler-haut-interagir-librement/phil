<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

/**
 * The number of calls `polyfilled-string.php` sent to the polyfills, so that a test can tell they did the work.
 */
final class PolyfillCalls
{
    public static int $count = 0;
}
