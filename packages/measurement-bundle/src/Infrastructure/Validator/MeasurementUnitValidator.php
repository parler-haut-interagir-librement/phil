<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Validator;

use Override;
use Phil\Measurement\Measurement;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementUnit;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks the family and the unit of the measurement against `MeasurementUnit`.
 */
final class MeasurementUnitValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MeasurementUnit) {
            throw new UnexpectedTypeException($constraint, MeasurementUnit::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof Measurement) {
            throw new UnexpectedValueException($value, Measurement::class);
        }

        if (null !== $constraint->family && $constraint->family !== $value->unit->family) {
            $this->context->buildViolation($constraint->familyMessage)
                ->setParameter('{{ family }}', $constraint->family)
                ->setCode(MeasurementUnit::FAMILY_ERROR)
                ->addViolation();

            return;
        }

        if ([] !== $constraint->units && !\in_array($value->unit->code, $constraint->units, true)) {
            $this->context->buildViolation($constraint->unitMessage)
                ->setParameter('{{ unit }}', $value->unit->code)
                ->setCode(MeasurementUnit::UNIT_ERROR)
                ->addViolation();
        }
    }
}
