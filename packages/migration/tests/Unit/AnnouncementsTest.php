<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit;

use Closure;
use Phil\Migration\Announcements;
use Phil\Migration\InvalidAnnouncement;
use Phil\Migration\Locale;
use Phil\Migration\News;
use Phil\Migration\Release;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Announcements::class)]
#[CoversClass(Release::class)]
#[CoversClass(News::class)]
#[CoversClass(Locale::class)]
#[CoversClass(InvalidAnnouncement::class)]
final class AnnouncementsTest extends TestCase
{
    public function testTheReleaseFollowsTheMigrationsGiven(): void
    {
        $calendar = new News('  Le calendrier ', ['Tous les événements.'], 'https://example.com');
        $announcements = new Announcements();
        $announcements->note('Second', ['fr' => '* Deux.']);
        $announcements->note('First', ['fr' => '* Un.', 'en_CA' => '* One.']);
        $announcements->note('First', ['en_CA' => '* Only in English.']);
        $announcements->note('Skipped', ['fr' => '* Jamais.']);
        $announcements->news('Second', ['fr' => $calendar]);

        $release = $announcements->release(['First', 'Second', 'Plain']);

        self::assertSame(['* Un.', '* Deux.'], $release->notesIn('fr'));
        self::assertSame(['* One.', '* Only in English.'], $release->notesIn('en_CA'));
        self::assertSame([$calendar], $release->newsIn('fr'));
        self::assertSame([], $release->newsIn('en_CA'));
        self::assertSame(['Le calendrier', ['Tous les événements.'], 'https://example.com'], [$calendar->title, $calendar->lines, $calendar->url]);
        self::assertFalse($release->isEmpty());
        self::assertTrue($announcements->release(['First', 'Second', 'Skipped'])->isEmpty());
    }

    public function testAReleaseIsEmptyWithoutNotesAndNews(): void
    {
        $announcements = new Announcements();
        $announcements->news('First', ['fr' => new News('Nouvelle')]);

        $news = $announcements->release(['First']);

        self::assertSame([[], [], null], [$news->notesIn('fr'), $news->newsIn('fr')[0]->lines, $news->newsIn('fr')[0]->url]);
        self::assertFalse($news->isEmpty());
        self::assertFalse(new Release([['fr' => 'x']])->isEmpty());
        self::assertTrue(new Release()->isEmpty());

        $announcements->note('First', ['fr' => 'x']);
        $announcements->clear();
        self::assertTrue($announcements->release(['First'])->isEmpty());
    }

    /**
     * @return iterable<string, array{Closure(Announcements): mixed, string}>
     */
    public static function badAnnouncements(): iterable
    {
        yield 'no locale' => [static fn (Announcements $announcements) => $announcements->note('M', []), 'An announcement is written in one locale at least.'];
        yield 'no news locale' => [static fn (Announcements $announcements) => $announcements->news('M', []), 'An announcement is written in one locale at least.'];
        yield 'a blank note' => [static fn (Announcements $announcements) => $announcements->note('M', ['fr' => 'x', 'en' => '  ']), 'A release note is a line of text.'];
        yield 'a bad locale' => [static fn (Announcements $announcements) => $announcements->note('M', ['fr-CA' => 'x']), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "fr-CA" is not.'];
        yield 'a bad news locale' => [static fn (Announcements $announcements) => $announcements->news('M', ['FR' => new News('x')]), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "FR" is not.'];
        yield 'a short locale' => [static fn (): string => Locale::check('f'), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "f" is not.'];
        yield 'a locale and more' => [static fn (): string => Locale::check("fr\n"), "A locale is a language, and maybe a region: \"fr\" or \"fr_CA\"; \"fr\n\" is not."];
        yield 'a locale after a digit' => [static fn (): string => Locale::check('1fr'), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "1fr" is not.'];
        yield 'a locale after more' => [static fn (): string => Locale::check('xfr_CAx'), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "xfr_CAx" is not.'];
        yield 'a news without title' => [static fn (): News => new News('  '), 'A piece of news has a title.'];
        yield 'a news with an empty url' => [static fn (): News => new News('x', [], ''), 'The address of a piece of news is a URL, or none.'];
    }

    #[DataProvider('badAnnouncements')]
    public function testABadAnnouncementIsRefused(Closure $announce, string $message): void
    {
        $this->expectException(InvalidAnnouncement::class);
        $this->expectExceptionMessageIsOrContains($message);

        $announce(new Announcements());
    }

    public function testLocales(): void
    {
        self::assertSame(['fr', 'ast', 'fr_CA'], [Locale::check('fr'), Locale::check('ast'), Locale::check('fr_CA')]);
    }
}
