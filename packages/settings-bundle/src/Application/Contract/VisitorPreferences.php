<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Contract;

/**
 * What the visitor of the current request chose of the settings kept for visitors, by key; the bundle checks each
 * value again against its setting before it uses it.
 */
interface VisitorPreferences
{
    /**
     * @return array<string, mixed>
     */
    public function values(): array;
}
