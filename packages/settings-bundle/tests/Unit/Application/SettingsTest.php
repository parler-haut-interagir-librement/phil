<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\SettingsBundle\Application\Event\SettingsChanged;
use Phil\SettingsBundle\Application\SettingPreview;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\CountingRepository;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedVisitor;
use Phil\SettingsBundle\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Settings::class)]
#[CoversClass(SettingsChanged::class)]
#[CoversClass(SettingPreview::class)]
final class SettingsTest extends TestCase
{
    private CountingRepository $repository;

    private FixedUser $user;

    private FixedVisitor $visitor;

    private RecordingEvents $events;

    private Settings $settings;

    protected function setUp(): void
    {
        $this->repository = new CountingRepository();
        $this->user = new FixedUser('ada');
        $this->visitor = new FixedVisitor();
        $this->events = new RecordingEvents();
        $this->settings = new Settings(Catalogs::sample(), $this->repository, $this->user, $this->visitor, new MockClock('2026-11-08 12:00:00 UTC'), $this->events);
    }

    public function testAValueNeverChosenIsItsDefault(): void
    {
        self::assertSame('system', $this->settings->get('display.motion'));
        self::assertSame(2, $this->settings->int('author.columns'));
        self::assertFalse($this->settings->bool('author.public'));
        self::assertSame('', $this->settings->string('author.signature'));
        self::assertSame('never', $this->settings->string('notifications.digest'));
        self::assertSame(['fantasy'], $this->settings->list('author.genres'));
        self::assertTrue($this->settings->bool('site.open'));
        self::assertSame(2, $this->repository->reads, 'the values of the current user and of the site are read once each');
    }

    public function testSavedValuesAreReadTypedAndOnlyWhatChangedIsStored(): void
    {
        $changed = $this->settings->save(Subject::user('ada'), ['author.columns' => 3, 'author.public' => true, 'author.signature' => 'Ada', 'author.genres' => ['horror', 'fantasy'], 'display.motion' => 'system']);

        self::assertSame(['author.columns', 'author.public', 'author.signature', 'author.genres'], $changed);
        self::assertCount(1, $this->repository->saves);
        self::assertSame(['user:ada', ['author.columns' => '3', 'author.public' => 'true', 'author.signature' => '"Ada"', 'author.genres' => '["fantasy","horror"]'], []], \array_slice($this->repository->saves[0], 0, 3));
        self::assertEquals(new DateTimeImmutable('2026-11-08 12:00:00 UTC'), $this->repository->saves[0][3]);
        self::assertEquals([new SettingsChanged('user', 'ada', $changed)], $this->events->events);

        self::assertSame(3, $this->settings->int('author.columns'));
        self::assertTrue($this->settings->bool('author.public'));
        self::assertSame('Ada', $this->settings->string('author.signature'));
        self::assertSame(['fantasy', 'horror'], $this->settings->list('author.genres'));
        self::assertSame(3, $this->settings->get('author.columns', 'ada'));
        self::assertSame(2, $this->settings->get('author.columns', 'bob'), 'another user has the default');
    }

    public function testAValueBackToItsDefaultIsRemoved(): void
    {
        $subject = Subject::user('ada');
        $this->settings->save($subject, ['author.columns' => 3, 'author.public' => true]);

        self::assertSame(['author.columns'], $this->settings->save($subject, ['author.columns' => 2, 'author.public' => true]));
        self::assertSame([[], ['author.columns']], [$this->repository->saves[1][1], $this->repository->saves[1][2]]);
        self::assertSame(['author.public' => 'true'], $this->repository->inner->values($subject));
        self::assertSame(2, $this->settings->int('author.columns'));

        self::assertSame(['author.public'], $this->settings->restoreDefaults($subject, ['author.public', 'author.columns']));
        self::assertSame([], $this->repository->inner->values($subject));
        self::assertCount(3, $this->events->events);
    }

    public function testNothingChangedSavesNothing(): void
    {
        self::assertSame([], $this->settings->save(Subject::user('ada'), ['author.columns' => 2]));
        self::assertSame(['author.public'], $this->settings->save(Subject::user('bob'), ['author.columns' => 2, 'author.public' => true]), 'an unchanged value is passed over');
        $this->repository->saves = [];
        $this->events->events = [];
        self::assertSame([], $this->settings->save(Subject::user('ada'), []));
        self::assertSame([], $this->repository->saves);
        self::assertSame([], $this->events->events);
    }

    public function testTheValuesOfASubjectAreReadOnceUntilTheyChangeOrTheServiceIsReset(): void
    {
        $this->settings->get('author.columns');
        $this->settings->get('author.public');
        self::assertSame(1, $this->repository->reads);

        $this->settings->save(Subject::user('ada'), ['author.columns' => 4]);
        self::assertSame(4, $this->settings->get('author.columns'));
        self::assertSame(2, $this->repository->reads, 'compared with the values read, then read again once changed');

        $this->repository->inner->save(Subject::user('ada'), ['author.columns' => '1'], [], new DateTimeImmutable());
        self::assertSame(4, $this->settings->get('author.columns'), 'the values are kept for the request');

        $this->settings->reset();
        self::assertSame(1, $this->settings->get('author.columns'));
    }

    public function testTheSiteSettingsAreOneForAll(): void
    {
        self::assertSame(['site.motto'], $this->settings->save(Subject::site(), ['site.motto' => 'Welcome']));
        $this->user->id = null;

        self::assertSame('Welcome', $this->settings->string('site.motto'));
        self::assertSame('Welcome', $this->settings->get('site.motto', 'bob'));
        self::assertEquals([new SettingsChanged('site', 'site', ['site.motto'])], $this->events->events);
    }

    public function testAVisitorHasTheDefaultsOrTheirCookie(): void
    {
        $this->user->id = null;
        $this->visitor->values = ['display.motion' => 'reduce', 'display.theme' => 'sepia', 'author.public' => true, 'author.signature' => 'Ada'];

        self::assertSame('reduce', $this->settings->get('display.motion'));
        self::assertSame('system', $this->settings->get('display.theme'), 'a refused value is the default');
        self::assertTrue($this->settings->bool('author.public'));
        self::assertSame('', $this->settings->string('author.signature'), 'a setting not kept for visitors is the default');
        self::assertSame(0, $this->repository->reads);

        self::assertSame(['display.motion' => 'reduce', 'author.columns' => 2], $this->settings->values(null, [Catalogs::sample()->get('display.motion'), Catalogs::sample()->get('author.columns')]));
    }

    public function testTheValuesOfASubject(): void
    {
        $this->settings->save(Subject::user('bob'), ['author.columns' => 4]);

        self::assertSame(['author.columns' => 4, 'author.public' => false], $this->settings->values(Subject::user('bob'), [Catalogs::sample()->get('author.columns'), Catalogs::sample()->get('author.public')]));
    }

    public function testAStoredValueThatNoLongerKeepsTheRulesIsTheDefault(): void
    {
        $this->repository->inner->save(Subject::user('ada'), ['author.columns' => '9', 'display.motion' => '"spin"'], [], new DateTimeImmutable());

        self::assertSame(2, $this->settings->int('author.columns'));
        self::assertSame('system', $this->settings->string('display.motion'));
    }

    public function testASubjectIsForgotten(): void
    {
        $this->settings->save(Subject::user('ada'), ['author.columns' => 4, 'author.public' => true]);
        $this->settings->get('author.columns');

        self::assertSame(['author.columns', 'author.public'], $this->settings->forget(Subject::user('ada')));
        self::assertSame(2, $this->settings->int('author.columns'));
        self::assertEquals(new SettingsChanged('user', 'ada', ['author.columns', 'author.public']), $this->events->events[1]);

        self::assertSame([], $this->settings->forget(Subject::user('ada')));
        self::assertCount(2, $this->events->events, 'nothing erased is no change');
    }

    public function testARefusedValueChangesNothing(): void
    {
        try {
            $this->settings->save(Subject::user('ada'), ['author.columns' => 3, 'author.signature' => str_repeat('x', 21)]);
            self::fail('The value is accepted.');
        } catch (InvalidSettingValue $invalidSettingValue) {
            self::assertSame('author.signature', $invalidSettingValue->key);
        }

        self::assertSame([], $this->repository->saves);
    }

    public function testASettingOfAnotherScopeIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "site.open" is not a user setting.');

        $this->settings->save(Subject::user('ada'), ['site.open' => false]);
    }

    public function testAnUnknownSettingIsRefused(): void
    {
        $this->expectException(UnknownSetting::class);

        $this->settings->get('author.age');
    }

    public function testASettingIsReadAsItsType(): void
    {
        foreach ([
            'The setting "author.columns" is not read as a boolean.' => fn (): bool => $this->settings->bool('author.columns'),
            'The setting "author.public" is not read as a integer.' => fn (): int => $this->settings->int('author.public'),
            'The setting "author.columns" is not read as a text.' => fn (): string => $this->settings->string('author.columns'),
            'The setting "display.motion" is not read as a choices.' => fn (): array => $this->settings->list('display.motion'),
        ] as $message => $read) {
            try {
                $read();
                self::fail($message);
            } catch (InvalidSetting $invalidSetting) {
                self::assertSame($message, $invalidSetting->getMessage());
            }
        }
    }

    public function testWithoutDispatcherChangesAreSavedAllTheSame(): void
    {
        $settings = new Settings(Catalogs::sample(), $this->repository, $this->user, $this->visitor, new MockClock());

        self::assertSame(['author.columns'], $settings->save(Subject::user('ada'), ['author.columns' => 3]));
        self::assertSame(['author.columns'], $settings->forget(Subject::user('ada')));
    }

    public function testAPreviewSaysWhatSavingWouldDoWithoutSaving(): void
    {
        $this->settings->save(Subject::user('ada'), ['author.columns' => 3]);
        $catalog = Catalogs::sample();
        $definitions = [$catalog->get('author.columns'), $catalog->get('author.signature'), $catalog->get('author.genres')];
        $previews = $this->settings->preview(Subject::user('ada'), $definitions, ['author.columns' => 2, 'author.signature' => 'Ada']);

        self::assertSame(
            [['author.columns', 3, 2, true], ['author.signature', '', 'Ada', true], ['author.genres', ['fantasy'], ['fantasy'], false]],
            array_map(static fn (SettingPreview $preview): array => [$preview->definition->key, $preview->current, $preview->proposed, $preview->changed], $previews),
        );
        self::assertSame(['author.columns' => '3'], $this->repository->values(Subject::user('ada')));
        self::assertCount(1, $this->events->events);
    }

    public function testAVisitorsPreviewStartsFromTheirValues(): void
    {
        $this->visitor->values = ['author.public' => true];
        $definition = Catalogs::sample()->get('author.public');

        $previews = $this->settings->preview(null, [$definition], ['author.public' => false]);

        self::assertSame([true, false, true], [$previews[0]->current, $previews[0]->proposed, $previews[0]->changed]);
    }

    public function testAPreviewRefusesAValueTheRulesRefuse(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->settings->preview(Subject::user('ada'), [Catalogs::sample()->get('author.columns')], ['author.columns' => 9]);
    }
}
