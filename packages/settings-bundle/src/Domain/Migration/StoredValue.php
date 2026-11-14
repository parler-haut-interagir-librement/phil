<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Migration;

/**
 * One stored value of a key, as a migration reads and writes it: whose it is, by scope and subject, and its JSON, or
 * null when the migration removes it.
 */
final readonly class StoredValue
{
    public function __construct(
        public string $scope,
        public string $subject,
        public string $key,
        public ?string $value,
    ) {
    }
}
