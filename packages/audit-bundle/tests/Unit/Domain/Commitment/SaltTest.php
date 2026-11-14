<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Commitment;

use Phil\AuditBundle\Domain\Commitment\Salt;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Salt::class)]
final class SaltTest extends TestCase
{
    public function testAGeneratedSaltIsExactlyTheMinimum(): void
    {
        self::assertSame(16, \strlen(Salt::generate()->bytes));
    }

    public function testTwoGeneratedSaltsDiffer(): void
    {
        self::assertNotSame(Salt::generate()->bytes, Salt::generate()->bytes);
    }

    public function testAStoredSaltOfTheMinimumLengthIsAccepted(): void
    {
        $bytes = str_repeat("\x01", 16);

        self::assertSame($bytes, Salt::fromBytes($bytes)->bytes);
    }

    public function testALongerStoredSaltIsAccepted(): void
    {
        $bytes = str_repeat("\x01", 32);

        self::assertSame($bytes, Salt::fromBytes($bytes)->bytes);
    }

    /**
     * 128 bits is the floor: one byte less and the salt is refused, wherever it comes from.
     */
    public function testAStoredSaltShorterThanTheMinimumIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A salt is at least 16 bytes; this one is 15.');

        Salt::fromBytes(str_repeat("\x01", 15));
    }
}
