<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemorySettingRepository::class)]
final class InMemorySettingRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function repository(): SettingRepository&SettingMigrationStore
    {
        return new InMemorySettingRepository();
    }
}
