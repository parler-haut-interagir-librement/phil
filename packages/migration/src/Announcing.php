<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * A migration that announces what it brings: the `AnnouncingMigrationFactory` gives it the announcements of the run.
 */
interface Announcing
{
    public function announceWith(Announcements $announcements): void;
}
