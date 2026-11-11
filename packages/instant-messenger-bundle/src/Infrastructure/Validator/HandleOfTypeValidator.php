<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Infrastructure\Validator;

use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\Messengers;
use Phil\InstantMessenger\MessengerType;
use Phil\InstantMessengerBundle\Application\AcceptedMessengers;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class HandleOfTypeValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof HandleOfType) {
            throw new UnexpectedTypeException($constraint, HandleOfType::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $sets = AcceptedMessengers::sets($constraint->types);

        $accepted = match (true) {
            $value instanceof Handle => array_any($sets, static fn (EnumSet $set): bool => $value->type::class === $set->enum() && $set->contains($value->type)),
            \is_string($value) => Messengers::detect($value, ...$sets) instanceof Handle,
            default => throw new UnexpectedValueException($value, 'string|Phil\\InstantMessenger\\Handle'),
        };

        if (!$accepted) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ types }}', implode(', ', array_map(static fn (MessengerType $type): string => $type->name, AcceptedMessengers::cases($constraint->types))))
                ->setCode(HandleOfType::NOT_ACCEPTED_ERROR)
                ->addViolation();
        }
    }
}
