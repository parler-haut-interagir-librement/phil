<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\IdempotencyScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyScope::class)]
final class IdempotencyScopeTest extends TestCase
{
    public function testTwoCallersAreTwoScopes(): void
    {
        self::assertTrue(IdempotencyScope::of('client-a')->equals(IdempotencyScope::of('client-a')));
        self::assertFalse(IdempotencyScope::of('client-a')->equals(IdempotencyScope::of('client-b')));
    }

    public function testTheLongestScopeIsAccepted(): void
    {
        self::assertSame(255, \strlen(IdempotencyScope::of(str_repeat('a', 255))->value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badScopes(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'control character' => ["a\0b"];
    }

    #[DataProvider('badScopes')]
    public function testABadScopeIsRefused(string $value): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        IdempotencyScope::of($value);
    }
}
