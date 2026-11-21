<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Exception\UndeliverableRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(InvalidRecord::class)]
#[CoversClass(UndeliverableRecord::class)]
#[CoversClass(ForgottenRecord::class)]
final class ExceptionsTest extends TestCase
{
    public function testTheExceptionsCarryTheReasonThePreviousExceptionAndNoCode(): void
    {
        $previous = new RuntimeException('previous');

        foreach ([InvalidRecord::because('Out of bounds.', $previous), UndeliverableRecord::because('Gone.', $previous)] as $exception) {
            self::assertSame($previous, $exception->getPrevious());
            self::assertSame(0, $exception->getCode());
        }

        self::assertSame('Out of bounds.', InvalidRecord::because('Out of bounds.')->getMessage());
        self::assertNull(UndeliverableRecord::because('Gone.')->getPrevious());
    }

    public function testAForgottenRecordCarriesItsReasonOnly(): void
    {
        $exception = ForgottenRecord::because('The request was erased.');

        self::assertSame('The request was erased.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
}
