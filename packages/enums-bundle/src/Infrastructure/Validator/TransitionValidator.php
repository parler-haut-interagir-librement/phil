<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\Enums\Meta\Reflection;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use UnitEnum;

/**
 * Checks a `Transition` against the `#[TransitionsTo]` of the previous case.
 */
final class TransitionValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Transition) {
            throw new UnexpectedTypeException($constraint, Transition::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof UnitEnum) {
            throw new UnexpectedValueException($value, UnitEnum::class);
        }

        $previous = $this->previous($constraint, $value);

        if (!$previous instanceof UnitEnum) {
            if ($constraint->fromInitial && !$this->isInitial($value)) {
                $this->context->buildViolation($constraint->initialMessage)
                    ->setParameter('{{ to }}', $this->formatValue($value->name))
                    ->setCode(Transition::NOT_INITIAL_ERROR)
                    ->addViolation();
            }

            return;
        }

        if ($previous !== $value && !\in_array($value, Reflection::transitions($previous), true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ from }}', $this->formatValue($previous->name))
                ->setParameter('{{ to }}', $this->formatValue($value->name))
                ->setCode(Transition::NOT_ALLOWED_ERROR)
                ->addViolation();
        }
    }

    /**
     * The case the state had: on the object, as `from` names it, or as Doctrine loaded it — Doctrine keeps the
     * backed value, read back as its case.
     */
    private function previous(Transition $constraint, UnitEnum $value): mixed
    {
        $object = $this->context->getObject();

        if (!\is_object($object)) {
            return null;
        }

        if (null !== $constraint->from) {
            return new ReflectionProperty($object, $constraint->from)->getValue($object);
        }

        $manager = $this->doctrine?->getManagerForClass($object::class);

        if (!$manager instanceof EntityManagerInterface || !$manager->getUnitOfWork()->isInIdentityMap($object)) {
            return null;
        }

        $original = $manager->getUnitOfWork()->getOriginalEntityData($object)[(string) $this->context->getPropertyName()] ?? null;

        return $value instanceof BackedEnum && (\is_int($original) || \is_string($original)) ? $value::tryFrom($original) : $original;
    }

    /**
     * Whether no case of the enum moves to this one.
     */
    private function isInitial(UnitEnum $case): bool
    {
        return array_all($case::cases(), static fn (UnitEnum $other): bool => !\in_array($case, Reflection::transitions($other), true));
    }
}
