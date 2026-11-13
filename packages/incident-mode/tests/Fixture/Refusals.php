<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use PHPUnit\Framework\Assert;
use Throwable;

/**
 * A call refused with an exception of a class, whose message holds a fragment.
 */
trait Refusals
{
    /**
     * @param class-string<Throwable> $class
     */
    private static function assertRefused(string $class, string $fragment, callable $call): void
    {
        try {
            $call();
        } catch (Throwable $throwable) {
            Assert::assertInstanceOf($class, $throwable);
            Assert::assertStringContainsString($fragment, $throwable->getMessage());

            return;
        }

        Assert::fail(\sprintf('Nothing was refused; a %s holding "%s" was expected.', $class, $fragment));
    }
}
