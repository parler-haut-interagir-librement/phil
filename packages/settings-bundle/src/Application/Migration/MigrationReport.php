<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Migration;

/**
 * What a migration changed, or would change on a dry run: how many values it writes and how many it removes.
 */
final readonly class MigrationReport
{
    public function __construct(
        public string $version,
        public int $written,
        public int $removed,
        public bool $applied,
    ) {
    }
}
