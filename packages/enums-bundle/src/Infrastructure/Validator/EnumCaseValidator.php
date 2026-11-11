<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use BackedEnum;
use Override;
use ReflectionEnum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UnitEnum;

/**
 * Checks an `EnumCase`: the value, read as a case of the enum, is one of the selection.
 */
final class EnumCaseValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EnumCase) {
            throw new UnexpectedTypeException($constraint, EnumCase::class);
        }

        if (null === $value) {
            return;
        }

        $case = $this->caseOf($value, $constraint->selection->enum);

        if (!$case instanceof UnitEnum) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(EnumCase::NOT_A_CASE_ERROR)
                ->addViolation();

            return;
        }

        if (!$constraint->selection->cases()->contains($case)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($case->name))
                ->setCode(EnumCase::NOT_ALLOWED_ERROR)
                ->addViolation();
        }
    }

    /**
     * The case the value stands for: the case itself, the case of a backed value, or the case of a name for a pure
     * enum; `null` when it stands for none.
     *
     * @param class-string<UnitEnum> $enum
     */
    private function caseOf(mixed $value, string $enum): ?UnitEnum
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof $enum ? $value : null;
        }

        if (is_subclass_of($enum, BackedEnum::class)) {
            $value = $this->backing($value, $enum);

            return null === $value ? null : $enum::tryFrom($value);
        }

        return \is_string($value) ? array_find($enum::cases(), static fn (UnitEnum $case): bool => $case->name === $value) : null;
    }

    /**
     * The value in the type that backs the enum, `tryFrom()` taking no other under strict types: a string for strings;
     * an integer, or a string that writes one as PHP does — `'3'`, `'-3'`, not `'03'` nor `'3.0'` —, for integers, as a
     * query string brings it; `null` for anything else.
     *
     * @param class-string<BackedEnum> $enum
     */
    private function backing(mixed $value, string $enum): int|string|null
    {
        if ('int' !== (string) new ReflectionEnum($enum)->getBackingType()) {
            return \is_string($value) ? $value : null;
        }

        return \is_int($value) || (\is_string($value) && (string) (int) $value === $value) ? (int) $value : null;
    }
}
