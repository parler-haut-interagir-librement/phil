<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\SettingsBundle\Application\Migration\MigrationReport;
use Phil\SettingsBundle\Application\Migration\MigrationValues;
use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\Migration\Step;
use Phil\SettingsBundle\Domain\Migration\StepKind;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(SettingMigrator::class)]
#[CoversClass(MigrationValues::class)]
#[CoversClass(MigrationReport::class)]
#[CoversClass(Step::class)]
#[CoversClass(StoredValue::class)]
final class SettingMigratorTest extends TestCase
{
    private InMemorySettingRepository $store;

    private SettingCatalog $catalog;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemorySettingRepository();
        $this->catalog = new SettingCatalog([new SectionDefinition('author'), new SectionDefinition('site')], [
            new SettingDefinition('author.signature', SettingType::Text, section: 'author', maxLength: 10),
            new SettingDefinition('author.layout', SettingType::Choice, 'list', section: 'author', choices: ['list', 'grid', 'cards']),
            new SettingDefinition('author.columns', SettingType::Integer, 3, section: 'author', min: 1, max: 6),
            new SettingDefinition('site.mode', SettingType::Choice, 'open', Scope::Site, 'site', choices: ['open', 'closed', 'invite']),
        ]);
    }

    public function testARenameMovesTheValuesTheNewSettingAccepts(): void
    {
        $this->seed(['kim' => ['author.sig' => '"old"', 'author.signature' => '"kept"']]);
        $this->store->save(Subject::site(), ['author.sig' => '"site"'], [], new DateTimeImmutable());
        $this->seed(['ada' => ['author.sig' => '"Ada"'], 'bob' => ['author.sig' => '"far too long a signature"'], 'eve' => ['author.sig' => '""'], 'lou' => ['author.sig' => 'not json']]);

        $reports = $this->migrator(['v1' => [Step::rename('author.sig', 'author.signature')]])->migrate();

        self::assertEquals([new MigrationReport('v1', 1, 6, true)], $reports);
        self::assertSame(['author.signature' => '"Ada"'], $this->store->values(Subject::user('ada')));
        self::assertSame([], $this->store->values(Subject::user('bob')), 'refused by the new rules');
        self::assertSame([], $this->store->values(Subject::user('eve')), 'equal to the default');
        self::assertSame(['author.signature' => '"kept"'], $this->store->values(Subject::user('kim')), 'a value of the new key wins');
        self::assertSame([], $this->store->values(Subject::user('lou')));
        self::assertSame([], $this->store->values(Subject::site()), 'a value of another scope is dropped');
        self::assertSame(['v1'], $this->store->appliedMigrations());
    }

    public function testAConversionChangesEachValueThroughItsFunction(): void
    {
        $this->seed(['kim' => ['author.layout' => '{'], 'eve' => ['author.layout' => '"tiles"'], 'ada' => ['author.layout' => 'true'], 'bob' => ['author.layout' => 'false']]);

        $migrator = $this->migrator(['v1' => [Step::convert('author.layout', static fn (mixed $old): mixed => match ($old) {
            true => 'grid',
            false => 'list',
            default => $old,
        })]]);

        self::assertEquals([new MigrationReport('v1', 1, 3, true)], $migrator->migrate());
        self::assertSame(['author.layout' => '"grid"'], $this->store->values(Subject::user('ada')));
        self::assertSame([], $this->store->values(Subject::user('bob')), 'the default is not stored');
        self::assertSame([], $this->store->values(Subject::user('eve')), 'refused');
        self::assertSame([], $this->store->values(Subject::user('kim')), 'not JSON');
    }

    public function testAValueThatStaysTheSameIsNotWritten(): void
    {
        $this->seed(['ada' => ['author.layout' => '"grid"']]);

        self::assertEquals([new MigrationReport('v1', 0, 0, true)], $this->migrator(['v1' => [Step::convert('author.layout', static fn (mixed $old): mixed => $old)]])->migrate());
        self::assertSame(['author.layout' => '"grid"'], $this->store->values(Subject::user('ada')));
    }

    public function testAChangedDefaultDropsTheValuesEqualToItAndKeepsTheSitesBehaviour(): void
    {
        $this->seed(['ada' => ['author.columns' => '3'], 'bob' => ['author.columns' => '2']]);

        $this->migrator(['v1' => [Step::defaultChanged('author.columns', 2), Step::defaultChanged('site.mode', 'closed')]])->migrate();

        self::assertSame([], $this->store->values(Subject::user('ada')));
        self::assertSame(['author.columns' => '2'], $this->store->values(Subject::user('bob')));
        self::assertSame(['site.mode' => '"closed"'], $this->store->values(Subject::site()));
    }

    public function testAChangedDefaultLeavesAStoredSiteValueAndCanLetTheNewDefaultApply(): void
    {
        $this->store->save(Subject::site(), ['site.mode' => '"invite"'], [], new DateTimeImmutable());
        $this->migrator(['v1' => [Step::defaultChanged('site.mode', 'closed')]])->migrate();
        self::assertSame(['site.mode' => '"invite"'], $this->store->values(Subject::site()));

        $this->store = new InMemorySettingRepository();
        $this->migrator(['v1' => [Step::defaultChanged('site.mode', 'closed', keepForSite: false)]])->migrate();
        self::assertSame([], $this->store->values(Subject::site()));

        $this->migrator(['v2' => [Step::defaultChanged('site.mode', 'open')]])->migrate();
        self::assertSame([], $this->store->values(Subject::site()), 'a previous default equal to the new one writes nothing');
    }

    public function testAPreviousDefaultTheSettingRefusesStopsTheMigration(): void
    {
        $this->expectExceptionObject(InvalidSetting::because('The previous default of the setting "site.mode" breaks its rules: The value of the setting "site.mode" is refused: it is not one of the choices.'));

        $this->migrator(['v1' => [Step::defaultChanged('site.mode', 'gone')]])->migrate();
    }

    public function testARemovalDropsEveryValueOfAKey(): void
    {
        $this->seed(['ada' => ['author.old' => '1', 'author.columns' => '4'], 'bob' => ['author.old' => '2']]);

        self::assertEquals([new MigrationReport('v1', 0, 2, true)], $this->migrator(['v1' => [Step::remove('author.old')]])->migrate());
        self::assertSame(['author.columns' => '4'], $this->store->values(Subject::user('ada')));
        self::assertSame([], $this->store->values(Subject::user('bob')));
    }

    public function testTheStepsOfAMigrationSeeTheChangesBeforeThem(): void
    {
        $this->seed(['ada' => ['author.cols' => '5']]);

        $this->migrator(['v1' => [Step::rename('author.cols', 'author.columns'), Step::convert('author.columns', static fn (mixed $old): mixed => \is_int($old) ? $old + 1 : $old)]])->migrate();

        self::assertSame(['author.columns' => '6'], $this->store->values(Subject::user('ada')));
    }

    public function testMigrationsRunInTheirOrderOnceAndADryRunWritesNothing(): void
    {
        $this->seed(['ada' => ['author.a' => '"Ada"']]);
        $this->catalogWith(new SettingDefinition('author.b', SettingType::Text, section: 'author'));
        $migrator = $this->migrator([
            'v1' => [Step::rename('author.a', 'author.b')],
            'v2' => [Step::rename('author.b', 'author.signature')],
        ], ['v2', 'v1']);

        self::assertSame(['v2', 'v1'], $migrator->pending());
        $this->store->applyMigration('v2', [], new DateTimeImmutable());
        self::assertSame(['v1'], $migrator->pending(), 'a list again, whichever version is applied');
        $this->store = new InMemorySettingRepository();
        $this->seed(['ada' => ['author.a' => '"Ada"']]);
        $migrator = $this->migrator([
            'v1' => [Step::rename('author.a', 'author.b')],
            'v2' => [Step::rename('author.b', 'author.signature')],
        ], ['v2', 'v1']);
        self::assertEquals([new MigrationReport('v2', 0, 0, false), new MigrationReport('v1', 1, 1, false)], $migrator->migrate(dryRun: true));
        self::assertSame(['author.a' => '"Ada"'], $this->store->values(Subject::user('ada')));
        self::assertSame([], $this->store->appliedMigrations());

        $migrator = $this->migrator([
            'v1' => [Step::rename('author.a', 'author.b')],
            'v2' => [Step::rename('author.b', 'author.signature')],
        ], ['v1', 'v2']);

        self::assertEquals([new MigrationReport('v1', 1, 1, true), new MigrationReport('v2', 1, 1, true)], $migrator->migrate());
        self::assertSame(['author.signature' => '"Ada"'], $this->store->values(Subject::user('ada')));
        self::assertSame([], $migrator->pending());
        self::assertSame([], $migrator->migrate(), 'run twice, it changes nothing');
    }

    public function testAStepNamingASettingNotDeclaredStopsTheMigration(): void
    {
        $this->expectException(UnknownSetting::class);

        $this->migrator(['v1' => [Step::rename('author.a', 'author.missing')]])->migrate();
    }

    public function testAStepKnowsWhatItDoes(): void
    {
        self::assertSame([StepKind::Rename, 'a.b', 'a.c'], [Step::rename('a.b', 'a.c')->kind, Step::rename('a.b', 'a.c')->key, Step::rename('a.b', 'a.c')->to]);
        self::assertSame([StepKind::DefaultChanged, 3, true], [Step::defaultChanged('a.b', 3)->kind, Step::defaultChanged('a.b', 3)->previous, Step::defaultChanged('a.b', 3)->keepForSite]);
        self::assertFalse(Step::rename('a.b', 'a.c')->keepForSite);
        self::assertFalse(Step::convert('a.b', static fn (mixed $value): mixed => $value)->keepForSite);
        self::assertFalse(Step::remove('a.b')->keepForSite);
        self::assertSame(StepKind::Remove, Step::remove('a.b')->kind);
        self::assertSame(StepKind::Convert, Step::convert('a.b', static fn (mixed $value): mixed => $value)->kind);

        $this->expectException(InvalidSetting::class);

        Step::rename('a.b', 'not a key');
    }

    /**
     * @param array<string, array<string, string>> $values by user, then key
     */
    private function seed(array $values): void
    {
        foreach ($values as $user => $stored) {
            $this->store->save(Subject::user($user), $stored, [], new DateTimeImmutable());
        }
    }

    private function catalogWith(SettingDefinition $setting): void
    {
        $this->catalog = new SettingCatalog([new SectionDefinition('author'), new SectionDefinition('site')], [...$this->catalog->settings(), $setting]);
    }

    /**
     * @param array<string, list<Step>> $migrations by version
     * @param list<string>|null         $order
     */
    private function migrator(array $migrations, ?array $order = null): SettingMigrator
    {
        $factories = array_map(static fn (array $steps): Closure => static fn (): SettingMigration => new readonly class($steps) implements SettingMigration {
            /**
             * @param list<Step> $steps
             */
            public function __construct(
                private array $steps,
            ) {
            }

            #[Override]
            public function steps(): array
            {
                return $this->steps;
            }
        }, $migrations);

        return new SettingMigrator($this->catalog, $this->store, new ServiceLocator($factories), $order ?? array_keys($migrations), new MockClock());
    }
}
