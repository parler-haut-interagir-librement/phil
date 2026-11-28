<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Support;

/**
 * The bounds given to `random_int()` by the code under test, recorded by tests/Support/random_int.php.
 */
final class RandomIntSpy
{
    /** @var list<array{int, int}> */
    public static array $bounds = [];
}
