<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Validator;

use Override;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Checks `AcceptedEmailAddress` by the library's `DeliverableEmailAddress`, built from the policy in force, in the
 * current context: the violations land on the same property, in the same group.
 */
final class AcceptedEmailAddressValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EmailPolicies $policies,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AcceptedEmailAddress) {
            throw new UnexpectedTypeException($constraint, AcceptedEmailAddress::class);
        }

        $this->context->getValidator()
            ->inContext($this->context)
            ->validate($value, $this->policies->for($constraint->list)->constraint([$this->context->getGroup() ?? Constraint::DEFAULT_GROUP]));
    }
}
