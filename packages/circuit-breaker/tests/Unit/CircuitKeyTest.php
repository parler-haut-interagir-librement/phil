<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitKey::class)]
final class CircuitKeyTest extends TestCase
{
    public function testAValidKeyIsKept(): void
    {
        self::assertSame('http.payments', CircuitKey::of('http.payments')->value);
        self::assertTrue(CircuitKey::of('a')->equals(CircuitKey::of('a')));
        self::assertFalse(CircuitKey::of('a')->equals(CircuitKey::of('b')));
    }

    #[TestWith([''])]
    #[TestWith(['Payments'])]
    #[TestWith(['https://example.com/hook'])]
    public function testAnInvalidKeyIsRefused(string $key): void
    {
        $this->expectException(InvalidCircuit::class);

        CircuitKey::of($key);
    }

    public function testAKeyOfTheMaximumLengthIsKept(): void
    {
        self::assertSame(87, CircuitKey::MAX_LENGTH);
        self::assertSame(str_repeat('k', 87), CircuitKey::of(str_repeat('k', 87))->value);
    }

    public function testATooLongKeyIsRefused(): void
    {
        $this->expectException(InvalidCircuit::class);

        CircuitKey::of(str_repeat('k', 88));
    }
}
