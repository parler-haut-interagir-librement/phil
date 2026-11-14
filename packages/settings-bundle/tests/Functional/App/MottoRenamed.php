<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional\App;

use Override;
use Phil\SettingsBundle\Domain\Attribute\AsSettingMigration;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Migration\Step;

/**
 * The motto, once kept under `site.tagline`.
 */
#[AsSettingMigration('2026-11-12.motto')]
final readonly class MottoRenamed implements SettingMigration
{
    #[Override]
    public function steps(): array
    {
        return [Step::rename('site.tagline', 'site.motto')];
    }
}
