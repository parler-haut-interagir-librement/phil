<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig;

use DateTimeZone;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\Incident\IncidentNoticeDraft;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each draft against a snapshot read by hand, in both languages, from a complete register and an organisation, and
 * from a line just recorded without one.
 */
#[CoversClass(TwigIncidentNotice::class)]
final class TwigIncidentNoticeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function drafts(): iterable
    {
        foreach (['commission', 'subject', 'public', 'follow_up'] as $kind) {
            foreach (['complete', 'incomplete'] as $register) {
                if ('follow_up' === $kind && 'incomplete' === $register) {
                    continue;
                }

                foreach (TwigIncidentNotice::LOCALES as $locale) {
                    yield \sprintf('%s, %s, %s', $kind, $register, $locale) => [\sprintf('%s-%s', $kind, $register), $locale];
                }
            }
        }
    }

    #[DataProvider('drafts')]
    public function testEachDraftMatchesItsSnapshot(string $name, string $locale): void
    {
        self::assertStringEqualsFile(\sprintf('%s/%s.%s.md', NoticeFixtures::SNAPSHOTS, $name, $locale), new TwigIncidentNotice(NoticeFixtures::twig())->render(self::draft($name), $locale, new DateTimeZone('America/Toronto')));
    }

    public function testDatesFollowTheTimeZoneAndDropAMidnight(): void
    {
        $draft = self::draft('commission-complete');

        $utc = new TwigIncidentNotice(NoticeFixtures::twig())->render($draft, 'en', new DateTimeZone('UTC'));

        self::assertStringContainsString('From 2026-11-08 05:00 to 2026-11-10 05:00 (approximate)', $utc);
        self::assertStringContainsString('On 2026-11-10 14:00', $utc);
        self::assertStringContainsString('notified on 2026-11-12 05:00.', $utc);
    }

    public function testTheTextIsWrittenAsTheRegisterHoldsIt(): void
    {
        $incident = NoticeFixtures::complete()->update(measures: '{{ nothing }} runs', summary: "<b>Mailing</b> & list\r\nexported");

        $markdown = new TwigIncidentNotice(NoticeFixtures::twig())->render(new IncidentNotices()->draft($incident, NoticeRecipient::Commission), 'en', new DateTimeZone('UTC'));

        self::assertStringContainsString("<b>Mailing</b> & list\nexported", $markdown);
        self::assertStringContainsString('{{ nothing }} runs', $markdown);
        self::assertStringEndsWith(")**\n", $markdown);
        self::assertStringStartsWith('# Notice to the Commission', $markdown);
        self::assertStringNotContainsString("\n\n\n", $markdown);
    }

    public static function draft(string $name): IncidentNoticeDraft
    {
        $source = new class implements NoticeOrganisationSource {
            public function organisation(): NoticeOrganisation
            {
                return NoticeFixtures::organisation();
            }
        };

        [$kind, $register] = explode('-', $name);
        $complete = 'complete' === $register;
        $notices = new IncidentNotices($complete ? $source : null);
        $reason = 'public' === $kind ? ($complete ? PublicNoticeReason::NoContactDetails : PublicNoticeReason::PromptAction) : null;
        $incident = $complete ? NoticeFixtures::complete($reason) : NoticeFixtures::incomplete($reason);

        return 'follow_up' === $kind
            ? $notices->followUp($incident, NoticeFixtures::revisions())
            : $notices->draft($incident, NoticeRecipient::from($kind));
    }
}
