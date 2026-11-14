<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Contract;

use Phil\SettingsBundle\Domain\Migration\Step;

/**
 * A versioned change of the stored values, declared with `#[AsSettingMigration]` beside the declaration it follows:
 * its steps run once, in their order, and the version is recorded with them.
 */
interface SettingMigration
{
    /**
     * @return list<Step>
     */
    public function steps(): array;
}
