<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use Override;
use Phil\Enums\Collection\EnumSet;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Checks an `EnumSetOf`: one violation for a value that is not a set of the enum; otherwise one for each case
 * outside the selection, and one for a count outside the bounds.
 */
final class EnumSetOfValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EnumSetOf) {
            throw new UnexpectedTypeException($constraint, EnumSetOf::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof EnumSet || $value->enum() !== $constraint->selection->enum) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(EnumSetOf::NOT_A_SET_ERROR)
                ->addViolation();

            return;
        }

        $allowed = $constraint->selection->cases();

        foreach ($value as $case) {
            if (!$allowed->contains($case)) {
                $this->context->buildViolation($constraint->notAllowedMessage)
                    ->setParameter('{{ value }}', $this->formatValue($case->name))
                    ->setCode(EnumSetOf::NOT_ALLOWED_ERROR)
                    ->addViolation();
            }
        }

        $count = \count($value);

        foreach ([[$constraint->min, $count < $constraint->min, $constraint->minMessage, EnumSetOf::TOO_FEW_ERROR], [$constraint->max, $count > $constraint->max, $constraint->maxMessage, EnumSetOf::TOO_MANY_ERROR]] as [$limit, $outside, $message, $code]) {
            if (null !== $limit && $outside) {
                $this->context->buildViolation($message)
                    ->setParameter('{{ count }}', (string) $count)
                    ->setParameter('{{ limit }}', (string) $limit)
                    ->setPlural($limit)
                    ->setCode($code)
                    ->addViolation();
            }
        }
    }
}
