<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * An object whose address is checked in the group `signup`.
 */
final readonly class Subscriber
{
    public function __construct(
        public string $email,
    ) {
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('email', new AcceptedEmailAddress(groups: ['signup']));
        $metadata->addPropertyConstraint('email', new NotBlank(groups: ['signup', 'Default']));
    }
}
