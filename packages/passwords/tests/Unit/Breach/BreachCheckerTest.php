<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Breach;

use Override;
use Phil\Passwords\Breach\BreachCheck;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Breach\BreachStatus;
use Phil\Passwords\Breach\Contract\BreachedPasswordSource;
use Phil\Passwords\Breach\InMemoryBreachSource;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

#[CoversClass(BreachChecker::class)]
#[CoversClass(BreachCheck::class)]
final class BreachCheckerTest extends TestCase
{
    public function testCompromisedFromTheThreshold(): void
    {
        $checker = new BreachChecker(InMemoryBreachSource::fromPasswords(['password' => 3]));

        $check = $checker->check('password');
        self::assertSame(BreachStatus::Compromised, $check->status);
        self::assertSame(3, $check->occurrences);
        self::assertTrue($check->isCompromised());
        self::assertFalse($check->isUnavailable());
        self::assertNull($check->failure);

        self::assertSame(BreachStatus::Compromised, $checker->check('password', 3)->status);
        self::assertSame(BreachStatus::NotFound, $checker->check('password', 4)->status);
        self::assertSame(3, $checker->check('password', 4)->occurrences);
    }

    public function testNotFound(): void
    {
        $check = new BreachChecker(InMemoryBreachSource::fromPasswords([]))->check('correct horse');

        self::assertSame(BreachStatus::NotFound, $check->status);
        self::assertSame(0, $check->occurrences);
        self::assertFalse($check->isCompromised());
    }

    public function testASourceThatCannotAnswer(): void
    {
        $failure = BreachCheckUnavailable::status(503);
        $source = new readonly class($failure) implements BreachedPasswordSource {
            public function __construct(
                private BreachCheckUnavailable $failure,
            ) {
            }

            #[Override]
            public function occurrences(#[SensitiveParameter] string $password): int
            {
                throw $this->failure;
            }
        };

        $check = new BreachChecker($source)->check('password');

        self::assertSame(BreachStatus::Unavailable, $check->status);
        self::assertTrue($check->isUnavailable());
        self::assertFalse($check->isCompromised());
        self::assertSame(0, $check->occurrences);
        self::assertSame($failure, $check->failure);
    }

    public function testTheDefaultThresholdIsOneOccurrence(): void
    {
        self::assertTrue(new BreachChecker(InMemoryBreachSource::fromPasswords(['hunter2' => 1]))->check('hunter2')->isCompromised());
    }
}
