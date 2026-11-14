<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application;

use Phil\SettingsBundle\Domain\Setting\SettingDefinition;

/**
 * One setting before a save: its value now, the value saving would give it, and whether they differ.
 */
final readonly class SettingPreview
{
    public bool $changed;

    /**
     * @param bool|int|string|list<string> $current
     * @param bool|int|string|list<string> $proposed
     */
    public function __construct(
        public SettingDefinition $definition,
        public bool|int|string|array $current,
        public bool|int|string|array $proposed,
    ) {
        $this->changed = $current !== $proposed;
    }
}
