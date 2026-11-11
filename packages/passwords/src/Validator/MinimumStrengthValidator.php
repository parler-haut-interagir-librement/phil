<?php

declare(strict_types=1);

namespace Phil\Passwords\Validator;

use Override;
use Phil\Passwords\Contract\PasswordUserInputs;
use Phil\Passwords\Strength\StrengthEstimator;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates `MinimumStrength` with the estimator it is built with — the bundled dictionaries and layouts, unless the
 * application registers it as a service with its own.
 */
final class MinimumStrengthValidator extends ConstraintValidator
{
    public function __construct(
        private readonly StrengthEstimator $estimator = new StrengthEstimator(),
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MinimumStrength) {
            throw new UnexpectedTypeException($constraint, MinimumStrength::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $object = $this->context->getObject();
        $strength = $this->estimator->estimate((string) $value, $object instanceof PasswordUserInputs ? $object->passwordUserInputs() : []);

        if ($strength->score->isAtLeast($constraint->minScore)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ score }}', (string) $strength->score->value)
            ->setParameter('{{ min_score }}', (string) $constraint->minScore->value)
            ->setParameter('{{ warning }}', $strength->feedback->warning?->translationKey() ?? '')
            ->setParameter('{{ suggestion }}', ($strength->feedback->suggestions[0] ?? null)?->translationKey() ?? '')
            ->setCode(MinimumStrength::TOO_WEAK_ERROR)
            ->setInvalidValue('')
            ->addViolation();
    }
}
