<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRange;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MoneyRangeValidator>
 */
#[CoversClass(MoneyRange::class)]
#[CoversClass(MoneyRangeValidator::class)]
final class MoneyRangeValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): MoneyRangeValidator
    {
        return new MoneyRangeValidator();
    }

    /**
     * @return iterable<string, array{?Money, MoneyRange}>
     */
    public static function inRange(): iterable
    {
        yield 'null' => [null, new MoneyRange(min: '0')];
        yield 'the minimum' => [self::cad(0), new MoneyRange(min: '0')];
        yield 'the maximum' => [self::cad(50_000), new MoneyRange(max: '500')];
        yield 'between' => [self::cad(100), new MoneyRange(min: '0.01', max: '500')];
        yield 'in the money’s own currency' => [Money::of(1_200, Currency::of('JPY')), new MoneyRange(min: '1200', max: '1200')];
        yield 'a minimum finer than the currency, rounded up' => [self::jpy(1), new MoneyRange(min: '0.01')];
        yield 'a maximum finer than the currency, rounded down' => [self::jpy(9), new MoneyRange(max: '9.99')];
        yield 'a negative minimum finer than the currency, rounded up' => [self::jpy(0), new MoneyRange(min: '-0.5')];
        yield 'a negative maximum finer than the currency, rounded down' => [self::jpy(-1), new MoneyRange(max: '-0.5')];
        yield 'trailing zeros are no precision' => [self::jpy(5), new MoneyRange(min: '5.000', max: '5.000')];
        yield 'a bound with as many decimals as the currency' => [self::cad(10), new MoneyRange(min: '0.10', max: '0.10')];
        yield 'a bound finer than cents, rounded inwards' => [self::cad(2), new MoneyRange(min: '0.015', max: '0.025')];
        yield 'a minimum below every amount' => [self::jpy(-9_000_000), new MoneyRange(min: '-99999999999999999999')];
        yield 'a maximum above every amount' => [self::jpy(9_000_000), new MoneyRange(max: '99999999999999999999')];
        yield 'a maximum rounded up past every amount' => [self::jpy(\PHP_INT_MAX), new MoneyRange(max: '9223372036854775807.5')];
    }

    #[DataProvider('inRange')]
    public function testAnAmountInTheRangeIsValid(?Money $value, MoneyRange $constraint): void
    {
        $this->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testAnAmountBelowTheMinimumIsAViolation(): void
    {
        $this->validate(self::cad(-1), new MoneyRange(min: '0', max: '500', minMessage: 'At least {{ limit }}.'));

        $this->buildViolation('At least {{ limit }}.')
            ->setParameter('{{ limit }}', '0.00 CAD')
            ->setCode(MoneyRange::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testAnAmountAboveTheMaximumIsAViolation(): void
    {
        $this->validate(self::cad(50_001), new MoneyRange(min: '0', max: '500', maxMessage: 'At most {{ limit }}.'));

        $this->buildViolation('At most {{ limit }}.')
            ->setParameter('{{ limit }}', '500.00 CAD')
            ->setCode(MoneyRange::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{Money, MoneyRange, string, string}>
     */
    public static function outOfRange(): iterable
    {
        yield 'below a minimum finer than cents' => [self::cad(1), new MoneyRange(min: '0.015'), '0.02 CAD', MoneyRange::TOO_LOW_ERROR];
        yield 'above a maximum finer than cents' => [self::cad(3), new MoneyRange(max: '0.025'), '0.02 CAD', MoneyRange::TOO_HIGH_ERROR];
        yield 'below a minimum finer than the currency' => [self::jpy(0), new MoneyRange(min: '0.01'), '1 JPY', MoneyRange::TOO_LOW_ERROR];
        yield 'above a maximum finer than the currency' => [self::jpy(10), new MoneyRange(max: '9.99'), '9 JPY', MoneyRange::TOO_HIGH_ERROR];
        yield 'below a negative minimum finer than the currency' => [self::jpy(-1), new MoneyRange(min: '-0.5'), '0 JPY', MoneyRange::TOO_LOW_ERROR];
        yield 'above a negative maximum finer than the currency' => [self::jpy(0), new MoneyRange(max: '-0.5'), '-1 JPY', MoneyRange::TOO_HIGH_ERROR];
        yield 'below a minimum above every amount' => [self::jpy(\PHP_INT_MAX), new MoneyRange(min: '99999999999999999999'), '99999999999999999999 JPY', MoneyRange::TOO_LOW_ERROR];
        yield 'below a minimum rounded up past every amount' => [self::jpy(\PHP_INT_MAX), new MoneyRange(min: '9223372036854775807.5'), '9223372036854775807.5 JPY', MoneyRange::TOO_LOW_ERROR];
        yield 'above a maximum below every amount' => [self::jpy(-\PHP_INT_MAX), new MoneyRange(max: '-99999999999999999999'), '-99999999999999999999 JPY', MoneyRange::TOO_HIGH_ERROR];
    }

    #[DataProvider('outOfRange')]
    public function testABoundIsReadInTheMoneysCurrency(Money $value, MoneyRange $constraint, string $limit, string $code): void
    {
        $this->validate($value, $constraint);

        $this->buildViolation(MoneyRange::TOO_LOW_ERROR === $code ? $constraint->minMessage : $constraint->maxMessage)
            ->setParameter('{{ limit }}', $limit)
            ->setCode($code)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function notDecimals(): iterable
    {
        yield 'a minimum' => ['0,01', null];
        yield 'a maximum' => [null, '1e3'];
        yield 'a minimum with a line after it' => ["1\n", null];
        yield 'a maximum with a plus' => [null, '+5'];
    }

    #[DataProvider('notDecimals')]
    public function testABoundIsADecimal(?string $min, ?string $max): void
    {
        $this->expectExceptionObject(new ConstraintDefinitionException(\sprintf('The bound "%s" of a money range is not a decimal.', $min ?? $max)));

        new MoneyRange(min: $min, max: $max);
    }

    public function testTheConstraintHasDefaultMessagesAndGroups(): void
    {
        $constraint = new MoneyRange(min: '0', groups: ['price']);

        self::assertSame('This amount should be {{ limit }} or more.', $constraint->minMessage);
        self::assertSame('This amount should be {{ limit }} or less.', $constraint->maxMessage);
        self::assertSame(['price'], $constraint->groups);
        self::assertSame('0', $constraint->min);
        self::assertNull($constraint->max);
        self::assertSame('TOO_LOW_ERROR', MoneyRange::getErrorName(MoneyRange::TOO_LOW_ERROR));
        self::assertSame('TOO_HIGH_ERROR', MoneyRange::getErrorName(MoneyRange::TOO_HIGH_ERROR));
    }

    public function testARangeNeedsABound(): void
    {
        $this->expectExceptionObject(new ConstraintDefinitionException('A money range needs a "min", a "max" or both.'));

        new MoneyRange();
    }

    public function testAnotherValueIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate(100, new MoneyRange(min: '0'));
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate(self::cad(1), new NotNull());
    }

    private static function jpy(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('JPY'));
    }

    private static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
