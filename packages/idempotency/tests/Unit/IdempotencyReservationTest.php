<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\IdempotencyScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyReservation::class)]
final class IdempotencyReservationTest extends TestCase
{
    public function testAReservationHoldsItsKeyUnderARandomToken(): void
    {
        $scope = IdempotencyScope::of('client:1');
        $key = IdempotencyKey::fromString('k-1');

        $reservation = IdempotencyReservation::create($scope, $key);

        self::assertSame([$scope, $key], [$reservation->scope, $reservation->key]);
        self::assertSame(32, IdempotencyReservation::TOKEN_LENGTH);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $reservation->token);
        self::assertNotSame($reservation->token, IdempotencyReservation::create($scope, $key)->token);
    }
}
