<?php

declare(strict_types=1);

namespace Phil\Migration;

use LogicException;

/**
 * A migration announced something, but nothing gave it the announcements of the run.
 */
final class AnnouncementsMissing extends LogicException
{
    public static function in(string $migration): self
    {
        return new self(\sprintf('%s announces, but has no announcements to add to: decorate the MigrationFactory with %s.', $migration, AnnouncingMigrationFactory::class));
    }
}
