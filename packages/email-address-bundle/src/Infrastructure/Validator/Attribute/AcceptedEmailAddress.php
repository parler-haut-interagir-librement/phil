<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Validator\Attribute;

use Attribute;
use Override;
use Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator;
use Symfony\Component\Validator\Constraint;

/**
 * An address the configured policy accepts: the library's `DeliverableEmailAddress` with the options of the bundle,
 * of the current site and of the list named, laid over each other. The violations are those of
 * `DeliverableEmailAddress`, with its codes and messages.
 *
 * Null and the empty string are valid: add `NotBlank` to require a value.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AcceptedEmailAddress extends Constraint
{
    /**
     * @param string|null       $list   the list whose policy applies, as the configuration's `lists` names it
     * @param list<string>|null $groups
     */
    public function __construct(
        public ?string $list = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }

    #[Override]
    public function validatedBy(): string
    {
        return AcceptedEmailAddressValidator::class;
    }

    #[Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
