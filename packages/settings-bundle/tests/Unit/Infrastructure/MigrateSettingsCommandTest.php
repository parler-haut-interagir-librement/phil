<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Migration\Step;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Console\MigrateSettingsCommand;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\RenameSignature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(MigrateSettingsCommand::class)]
final class MigrateSettingsCommandTest extends TestCase
{
    public function testADryRunSaysWhatWouldChangeThenTheRunAppliesItOnce(): void
    {
        $store = new InMemorySettingRepository();
        $store->save(Subject::user('ada'), ['author.sig' => '"Ada"'], [], new DateTimeImmutable());

        $tester = $this->tester($store, static fn (): SettingMigration => new RenameSignature());

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Version\s+Written\s+Removed/', $display);
        self::assertMatchesRegularExpression('/2026-11-12\.signature\s+1\s+1/', $display);
        self::assertStringContainsString('1 migration(s) would be applied; nothing is written.', $display);
        self::assertSame([], $store->appliedMigrations());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 migration(s) applied.', $tester->getDisplay());
        self::assertSame(['author.signature' => '"Ada"'], $store->values(Subject::user('ada')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Every migration of the settings is applied.', $tester->getDisplay());
        self::assertStringNotContainsString('Version', $tester->getDisplay(), 'nothing to list');
    }

    public function testAMigrationThatCannotRunFails(): void
    {
        $tester = $this->tester(new InMemorySettingRepository(), static fn (): SettingMigration => new class implements SettingMigration {
            public function steps(): array
            {
                return [Step::defaultChanged('site.motto', 3)];
            }
        });

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('The previous default of the setting "site.motto" breaks its rules', $tester->getDisplay());
    }

    /**
     * @param Closure(): SettingMigration $migration
     */
    private function tester(InMemorySettingRepository $store, Closure $migration): CommandTester
    {
        $migrator = new SettingMigrator(Catalogs::sample(), $store, new ServiceLocator(['2026-11-12.signature' => $migration]), ['2026-11-12.signature'], new MockClock());
        $application = new Application();
        $application->addCommand(new MigrateSettingsCommand($migrator));

        return new CommandTester($application->find('phil:settings:migrate'));
    }
}
