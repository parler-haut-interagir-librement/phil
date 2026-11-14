<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Infrastructure\Notifications\SettingsNotificationPreferences;
use Phil\SettingsBundle\Infrastructure\Privacy\SettingsPrivacy;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedVisitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SettingsNotificationPreferences::class)]
#[CoversClass(SettingsPrivacy::class)]
final class IntegrationsTest extends TestCase
{
    private InMemorySettingRepository $repository;

    private Settings $settings;

    protected function setUp(): void
    {
        $this->repository = new InMemorySettingRepository();
        $this->settings = new Settings(Catalogs::sample(), $this->repository, new FixedUser(), new FixedVisitor(), new MockClock());
    }

    public function testARecipientWhoNeverChoseHasTheDefaults(): void
    {
        $preferences = new SettingsNotificationPreferences($this->settings)->settingsFor('ada');

        self::assertSame([DigestFrequency::Never, null, [], null], [$preferences->digest, $preferences->quietHours, $preferences->optOuts, $preferences->locale]);
    }

    public function testTheNotificationPreferencesOfARecipient(): void
    {
        $this->settings->save(Subject::user('ada'), ['notifications.digest' => 'weekly', 'notifications.email' => false, 'notifications.web_push' => false, 'notifications.quiet_start' => '22:00', 'notifications.quiet_end' => '07:00']);
        $preferences = new SettingsNotificationPreferences($this->settings, 'America/Toronto')->settingsFor('ada');

        self::assertSame(DigestFrequency::Weekly, $preferences->digest);
        self::assertSame(['*:email', '*:web_push'], $preferences->optOuts);
        self::assertSame(['22:00', '07:00', 'America/Toronto'], [$preferences->quietHours?->start, $preferences->quietHours?->end, $preferences->quietHours?->timezone->getName()]);
        self::assertSame('UTC', new SettingsNotificationPreferences($this->settings)->settingsFor('ada')->quietHours?->timezone->getName());
    }

    public function testQuietHoursNeedTheirStartAndTheirEnd(): void
    {
        $this->settings->save(Subject::user('ada'), ['notifications.quiet_start' => '22:00', 'notifications.web_push' => false]);
        $this->settings->save(Subject::user('bob'), ['notifications.quiet_end' => '07:00', 'notifications.email' => false]);

        self::assertNull(new SettingsNotificationPreferences($this->settings)->settingsFor('ada')->quietHours);
        self::assertSame(['*:web_push'], new SettingsNotificationPreferences($this->settings)->settingsFor('ada')->optOuts);
        self::assertNull(new SettingsNotificationPreferences($this->settings)->settingsFor('bob')->quietHours);
        self::assertSame(['*:email'], new SettingsNotificationPreferences($this->settings)->settingsFor('bob')->optOuts);
    }

    public function testThePrivacyModuleExportsWhatThePersonChose(): void
    {
        $privacy = new SettingsPrivacy(Catalogs::sample(), $this->repository, $this->settings);
        $this->settings->save(Subject::user('ada'), ['display.motion' => 'reduce', 'author.genres' => ['horror'], 'author.columns' => 3]);
        $this->repository->save(Subject::user('ada'), ['gone.setting' => '"kept"', 'author.signature' => '7'], [], new DateTimeImmutable());

        self::assertSame('settings', $privacy->module()->value);
        self::assertSame(['settings' => [
            'author.columns' => 3,
            'author.genres' => ['horror'],
            'author.signature' => '7',
            'display.motion' => 'reduce',
            'gone.setting' => '"kept"',
        ]], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([], $privacy->export(DataSubject::fromString('bob')), 'nothing chosen');
        self::assertSame([], $privacy->export(DataSubject::fromString(str_repeat('x', 191))), 'no user has that identifier');
    }

    public function testThePrivacyModuleErasesEverything(): void
    {
        $privacy = new SettingsPrivacy(Catalogs::sample(), $this->repository, $this->settings);
        $this->settings->save(Subject::user('ada'), ['display.motion' => 'reduce']);
        $this->settings->save(Subject::user('bob'), ['display.motion' => 'reduce']);

        $report = $privacy->erase(DataSubject::fromString('ada'));

        self::assertSame(['settings', ['phil_settings' => ErasureEffect::Deleted]], [$report->module->value, $report->effects]);
        self::assertSame([], $this->repository->values(Subject::user('ada')));
        self::assertSame('system', $this->settings->get('display.motion', 'ada'));
        self::assertNotSame([], $this->repository->values(Subject::user('bob')));
        self::assertSame(['phil_settings' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString(str_repeat('x', 191)))->effects);
        self::assertSame(['phil_settings' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('ada'))->effects, 'idempotent');
    }
}
