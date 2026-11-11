<?php

declare(strict_types=1);

namespace Phil\Passwords\Validator;

use Override;
use Phil\Passwords\Breach\BreachChecker;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates `NotCompromised` with the breach checker it is built with: the application registers it as a service,
 * tagged `validator.constraint_validator`, with the checker of its source.
 */
final class NotCompromisedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly BreachChecker $checker,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotCompromised) {
            throw new UnexpectedTypeException($constraint, NotCompromised::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $check = $this->checker->check((string) $value, $constraint->threshold);

        if ($check->isUnavailable() && !$constraint->skipOnError) {
            $this->context->buildViolation($constraint->unavailableMessage)
                ->setCode(NotCompromised::UNAVAILABLE_ERROR)
                ->setInvalidValue('')
                ->addViolation();
        }

        if ($check->isCompromised()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ occurrences }}', (string) $check->occurrences)
                ->setCode(NotCompromised::COMPROMISED_ERROR)
                ->setInvalidValue('')
                ->addViolation();
        }
    }
}
