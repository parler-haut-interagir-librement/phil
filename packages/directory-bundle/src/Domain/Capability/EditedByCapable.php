<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

/**
 * A row that knows who wrote its version: the identifier of the user, kept with the version for its history.
 */
interface EditedByCapable
{
    public function getEditedBy(): ?string;

    public function setEditedBy(?string $editedBy): void;
}
