<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Validator;

use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\UnknownCurrency;
use Phil\Money\Money;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AcceptedCurrencyValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AcceptedCurrencies $accepted,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AcceptedCurrency) {
            throw new UnexpectedTypeException($constraint, AcceptedCurrency::class);
        }

        if (null === $value) {
            return;
        }

        $code = match (true) {
            $value instanceof Money => $value->currency->code,
            $value instanceof Currency => $value->code,
            \is_string($value) => $value,
            default => throw new UnexpectedValueException($value, Money::class.'|'.Currency::class.'|string'),
        };

        try {
            $accepted = $this->accepted->accepts(Currency::of($code));
        } catch (UnknownCurrency) {
            $accepted = false;
        }

        if (!$accepted) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ currency }}', $this->formatValue($code))
                ->setCode(AcceptedCurrency::NOT_ACCEPTED_ERROR)
                ->addViolation();
        }
    }
}
