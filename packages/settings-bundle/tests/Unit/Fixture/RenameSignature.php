<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Override;
use Phil\SettingsBundle\Domain\Attribute\AsSettingMigration;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Migration\Step;

/**
 * A migration of the tests: the signature, once kept under `author.sig`.
 */
#[AsSettingMigration('2026-11-12.signature')]
final readonly class RenameSignature implements SettingMigration
{
    #[Override]
    public function steps(): array
    {
        return [Step::rename('author.sig', 'author.signature')];
    }
}
