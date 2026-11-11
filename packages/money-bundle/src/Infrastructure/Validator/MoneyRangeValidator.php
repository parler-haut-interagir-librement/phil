<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Validator;

use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class MoneyRangeValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MoneyRange) {
            throw new UnexpectedTypeException($constraint, MoneyRange::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof Money) {
            throw new UnexpectedValueException($value, Money::class);
        }

        if (null !== $constraint->min) {
            $limit = $this->inward($constraint->min, $value->currency, up: true);

            if ($limit instanceof Money ? $value->isLessThan($limit) : !str_starts_with($constraint->min, '-')) {
                $this->violation($constraint->minMessage, $limit ?? $constraint->min, $value->currency, MoneyRange::TOO_LOW_ERROR);
            }
        }

        if (null !== $constraint->max) {
            $limit = $this->inward($constraint->max, $value->currency, up: false);

            if ($limit instanceof Money ? $value->isGreaterThan($limit) : str_starts_with($constraint->max, '-')) {
                $this->violation($constraint->maxMessage, $limit ?? $constraint->max, $value->currency, MoneyRange::TOO_HIGH_ERROR);
            }
        }
    }

    /**
     * A bound in a currency, rounded towards the inside of the range when it is finer than the currency counts — up
     * for a minimum, down for a maximum —, so that an amount of that currency is within the bound exactly when it is
     * within the rounded one; `null` when it lies beyond every amount of the currency.
     */
    private function inward(string $bound, Currency $currency, bool $up): ?Money
    {
        [$integer, $fraction] = explode('.', $bound) + [1 => ''];
        $digits = $currency->fractionDigits();
        $kept = substr($fraction, 0, $digits);

        try {
            $limit = Money::ofDecimal('' === $kept ? $integer : $integer.'.'.$kept, $currency);

            // Cut towards zero, the bound is already rounded inwards for a negative minimum and a positive maximum.
            if ('' === trim(substr($fraction, $digits), '0') || $up === str_starts_with($bound, '-')) {
                return $limit;
            }

            return $limit->add(Money::of($up ? 1 : -1, $currency));
        } catch (InvalidAmount) {
            return null;
        }
    }

    private function violation(string $message, Money|string $limit, Currency $currency, string $code): void
    {
        $this->context->buildViolation($message)
            ->setParameter('{{ limit }}', ($limit instanceof Money ? $limit->toDecimal() : $limit).' '.$currency->code)
            ->setCode($code)
            ->addViolation();
    }
}
