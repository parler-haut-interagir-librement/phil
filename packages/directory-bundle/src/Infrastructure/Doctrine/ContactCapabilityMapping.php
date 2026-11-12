<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\DirectoryBundle\Domain\Capability\ContactCapable;

/**
 * The columns of `ContactCapable`: the contact card, in the JSON column type of `phil/contact-bundle`.
 */
final class ContactCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return ContactCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [['fieldName' => 'contact', 'type' => 'phil_contact', 'columnName' => 'contact', 'nullable' => true]];
    }
}
