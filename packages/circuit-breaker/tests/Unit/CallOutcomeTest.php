<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallOutcome::class)]
final class CallOutcomeTest extends TestCase
{
    public function testAnOutcomeCarriesItsDuration(): void
    {
        self::assertSame([false, 120], [CallOutcome::succeeded(120)->failed, CallOutcome::succeeded(120)->milliseconds]);
        self::assertSame([true, 0], [CallOutcome::failed(0)->failed, CallOutcome::failed(0)->milliseconds]);
        self::assertNull(CallOutcome::succeeded()->milliseconds);
        self::assertNull(CallOutcome::failed()->milliseconds);
    }

    public function testANegativeDurationIsRefused(): void
    {
        $this->expectException(InvalidCircuit::class);
        $this->expectExceptionMessageIsOrContains('at least 0 milliseconds; got -1');

        CallOutcome::failed(-1);
    }

    public function testANegativeSuccessDurationIsRefused(): void
    {
        $this->expectException(InvalidCircuit::class);

        CallOutcome::succeeded(-1);
    }
}
