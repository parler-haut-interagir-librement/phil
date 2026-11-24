<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use DomainException;
use InvalidArgumentException;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\Exception\PricingException;
use Phil\Pricing\Exception\UndefinedRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AmountOutOfRange::class)]
#[CoversClass(InvalidPricing::class)]
#[CoversClass(InvalidRate::class)]
#[CoversClass(UndefinedRate::class)]
final class ExceptionsTest extends TestCase
{
    public function testEveryExceptionIsAPricingException(): void
    {
        self::assertContainsOnlyInstancesOf(PricingException::class, [AmountOutOfRange::intermediate(), InvalidPricing::negativePrice('-1.00'), InvalidRate::outOfRange(), UndefinedRate::zeroCost()]);
    }

    public function testInvalidArgumentsAndDomainErrorsAreTold(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, InvalidRate::outOfRange());
        self::assertInstanceOf(DomainException::class, InvalidPricing::negativePrice('-1.00'));
        self::assertInstanceOf(DomainException::class, UndefinedRate::zeroPrice());
        self::assertInstanceOf(DomainException::class, AmountOutOfRange::intermediate());
    }
}
