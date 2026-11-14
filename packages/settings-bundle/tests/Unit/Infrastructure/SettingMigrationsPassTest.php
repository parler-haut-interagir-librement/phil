<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Domain\Attribute\AsSettingMigration;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingMigrationsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingsServices;
use Phil\SettingsBundle\Tests\Unit\Fixture\RenameSignature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SettingMigrationsPass::class)]
#[CoversClass(AsSettingMigration::class)]
final class SettingMigrationsPassTest extends TestCase
{
    public function testTheMigratorGetsItsMigrationsByVersionInTheirOrder(): void
    {
        $container = $this->container();
        $container->register('app.c', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'c']);
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'a', 'after' => ['b', 7], 'before' => 'c']);
        $container->register('app.b', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'b', 'before' => ['c']]);
        new SettingMigrationsPass()->process($container);

        $migrator = $container->getDefinition(SettingMigrator::class);
        self::assertSame(['b', 'c', 'a'], $migrator->getArgument(3), 'a "before" that is not a list is passed over');
        $locator = $migrator->getArgument(2);
        self::assertInstanceOf(Reference::class, $locator);
        $factories = $container->getDefinition((string) $locator)->getArgument(0);
        self::assertIsArray($factories);
        self::assertSame(['c', 'a', 'b'], array_keys($factories));
        self::assertEquals(new ServiceClosureArgument(new Reference('app.a')), $factories['a']);
    }

    public function testAMigrationThatAsksToComeAfterAnotherDoes(): void
    {
        $container = $this->container();
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'a', 'after' => ['b']]);
        $container->register('app.b', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'b']);
        new SettingMigrationsPass()->process($container);

        self::assertSame(['b', 'a'], $container->getDefinition(SettingMigrator::class)->getArgument(3));
    }

    public function testNothingHappensWithoutTheMigrator(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG);
        new SettingMigrationsPass()->process($container);

        self::assertFalse($container->hasDefinition(SettingMigrator::class));
    }

    public function testAVersionMissingOrMalformedStopsTheContainer(): void
    {
        foreach ([[], ['version' => ''], ['version' => '-a'], ['version' => 'a b'], ['version' => str_repeat('a', 191)], ['version' => 3]] as $attributes) {
            $container = $this->container();
            $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, $attributes);

            try {
                new SettingMigrationsPass()->process($container);
                self::fail('The version is refused.');
            } catch (InvalidSetting $invalidSetting) {
                self::assertSame('The migration "app.a" has no version of letters, digits, ".", "_" and "-", of at most 190 characters.', $invalidSetting->getMessage());
            }
        }

        $container = $this->container();
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => str_repeat('a', 190)]);
        new SettingMigrationsPass()->process($container);
        self::assertSame([str_repeat('a', 190)], $container->getDefinition(SettingMigrator::class)->getArgument(3));
    }

    public function testAVersionGivenTwiceStopsTheContainer(): void
    {
        $container = $this->container();
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'v1']);
        $container->register('app.b', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'v1']);

        $this->expectExceptionObject(InvalidSetting::because('The version "v1" is given to two migrations.'));

        new SettingMigrationsPass()->process($container);
    }

    public function testVersionsInACircleStopTheContainer(): void
    {
        $container = $this->container();
        $container->register('app.a', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'a', 'before' => ['b']]);
        $container->register('app.b', RenameSignature::class)->addTag(SettingsServices::MIGRATION_TAG, ['version' => 'b', 'before' => ['a']]);

        $this->expectException(InvalidSetting::class);

        new SettingMigrationsPass()->process($container);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(SettingMigrator::class, SettingMigrator::class)->setArguments([null, null, null, [], null]);

        return $container;
    }
}
