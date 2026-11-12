<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\DirectoryBundle\Domain\Capability\EditedByCapable;

/**
 * The columns of `EditedByCapable`: who wrote the version.
 */
final class EditedByCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return EditedByCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [['fieldName' => 'editedBy', 'type' => 'string', 'columnName' => 'edited_by', 'length' => 255, 'nullable' => true]];
    }
}
