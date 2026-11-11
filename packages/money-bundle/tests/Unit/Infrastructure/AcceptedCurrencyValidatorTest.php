<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrency;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AcceptedCurrencyValidator>
 */
#[CoversClass(AcceptedCurrency::class)]
#[CoversClass(AcceptedCurrencyValidator::class)]
final class AcceptedCurrencyValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): AcceptedCurrencyValidator
    {
        return new AcceptedCurrencyValidator(AcceptedCurrencies::of(['CAD', 'USD']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function acceptedValues(): iterable
    {
        yield 'null' => [null];
        yield 'money' => [Money::of(100, Currency::of('CAD'))];
        yield 'currency' => [Currency::of('USD')];
        yield 'code' => ['USD'];
    }

    #[DataProvider('acceptedValues')]
    public function testAnAcceptedCurrencyIsValid(mixed $value): void
    {
        $this->validate($value, new AcceptedCurrency());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function refusedValues(): iterable
    {
        yield 'money' => [Money::of(100, Currency::of('EUR')), '"EUR"'];
        yield 'currency' => [Currency::of('EUR'), '"EUR"'];
        yield 'code' => ['EUR', '"EUR"'];
        yield 'unknown code' => ['XYZ', '"XYZ"'];
    }

    #[DataProvider('refusedValues')]
    public function testAnyOtherCurrencyIsAViolation(mixed $value, string $shown): void
    {
        $this->validate($value, new AcceptedCurrency(message: 'Not {{ currency }}.'));

        $this->buildViolation('Not {{ currency }}.')
            ->setParameter('{{ currency }}', $shown)
            ->setCode(AcceptedCurrency::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintHasADefaultMessageAndGroups(): void
    {
        $constraint = new AcceptedCurrency(groups: ['price']);

        self::assertSame('The currency {{ currency }} is not accepted.', $constraint->message);
        self::assertSame(['price'], $constraint->groups);
        self::assertSame('NOT_ACCEPTED_ERROR', AcceptedCurrency::getErrorName(AcceptedCurrency::NOT_ACCEPTED_ERROR));
    }

    public function testAnotherValueIsRefused(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException(new stdClass(), Money::class.'|'.Currency::class.'|string'));

        $this->validate(new stdClass(), new AcceptedCurrency());
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('CAD', new NotNull());
    }
}
