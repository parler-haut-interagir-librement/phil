<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

/**
 * @phpstan-require-implements EditedByCapable
 */
trait EditedByTrait
{
    private ?string $editedBy = null;

    public function getEditedBy(): ?string
    {
        return $this->editedBy;
    }

    public function setEditedBy(?string $editedBy): void
    {
        $this->editedBy = $editedBy;
    }
}
