<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Validator;

use Override;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementRange;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Compares the measurement with the bounds of `MeasurementRange`, read by the application's registry: a bound the
 * registry cannot read is a mistake of the constraint, never a violation.
 */
final class MeasurementRangeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UnitRegistry $registry,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MeasurementRange) {
            throw new UnexpectedTypeException($constraint, MeasurementRange::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof Measurement) {
            throw new UnexpectedValueException($value, Measurement::class);
        }

        foreach ([[$constraint->min, 1, $constraint->minMessage, MeasurementRange::TOO_LOW_ERROR], [$constraint->max, -1, $constraint->maxMessage, MeasurementRange::TOO_HIGH_ERROR]] as [$bound, $side, $message, $code]) {
            if (null === $bound) {
                continue;
            }

            $limit = $this->bound($bound);

            if ($limit->unit->family !== $value->unit->family) {
                $this->violation($constraint->familyMessage, $limit, MeasurementRange::FAMILY_ERROR);

                return;
            }

            if ($side === $limit->compareTo($value)) {
                $this->violation($message, $limit, $code);
            }
        }
    }

    private function bound(string $bound): Measurement
    {
        try {
            return $this->registry->parse($bound);
        } catch (MeasurementException $measurementException) {
            throw new ConstraintDefinitionException(\sprintf('The bound "%s" of a measurement range is not a measurement: %s', $bound, $measurementException->getMessage()), previous: $measurementException);
        }
    }

    private function violation(string $message, Measurement $limit, string $code): void
    {
        $this->context->buildViolation($message)
            ->setParameter('{{ limit }}', $limit->toString())
            ->setCode($code)
            ->addViolation();
    }
}
