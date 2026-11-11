<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Validator;

use Override;
use Phil\Address\Address;
use Phil\Address\AddressValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidAddressValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAddress) {
            throw new UnexpectedTypeException($constraint, ValidAddress::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof Address) {
            throw new UnexpectedValueException($value, Address::class);
        }

        foreach (AddressValidator::violations($value) as $violation) {
            $this->context->buildViolation(ValidAddress::MESSAGES[$violation->violation->value])
                ->atPath($violation->field->value)
                ->setCode(ValidAddress::CODES[$violation->violation->value])
                ->addViolation();
        }
    }
}
