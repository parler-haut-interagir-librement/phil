<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Application\ActivityType\LinkActivity;
use Phil\CoursesBundle\Application\ActivityType\PageActivity;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageActivity::class)]
#[CoversClass(LinkActivity::class)]
#[CoversClass(ScormActivity::class)]
#[CoversClass(Cmi5Activity::class)]
#[CoversClass(ContentUrls::class)]
#[CoversClass(Launch::class)]
final class ActivityTypesTest extends TestCase
{
    public function testAPageShowsItsBodyAndIsMarkedDone(): void
    {
        $page = new PageActivity();

        self::assertSame('page', $page->name());
        self::assertTrue($page->completesManually());
        self::assertSame(['body' => 'Hi'], $page->settings(['body' => 'Hi', 'other' => 1]));
        self::assertSame(['body' => ''], $page->settings([]));
        self::assertSame(LaunchWindow::Page, $page->launch($this->activity('page'), $this->enrollment())->window);
        self::assertNull($page->launch($this->activity('page'), $this->enrollment())->url);

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('The "body" of a page is text.');

        $page->settings(['body' => 3]);
    }

    public function testALinkOpensItsAddressInItsOwnWindow(): void
    {
        $link = new LinkActivity();

        self::assertSame('link', $link->name());
        self::assertTrue($link->completesManually());
        self::assertSame(['url' => 'https://example.org/a%20b'], $link->settings(['url' => 'https://EXAMPLE.org/a%20b']));
        $launch = $link->launch($this->activity('link', ['url' => 'https://example.org/']), $this->enrollment());
        self::assertSame(LaunchWindow::NewWindow, $launch->window);
        self::assertSame('https://example.org/', $launch->url);

        foreach ([[], ['url' => 3]] as $settings) {
            try {
                $link->settings($settings);
                self::fail('The link is accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A link names its "url".', $invalidCourse->getMessage());
            }
        }

        try {
            $link->settings(['url' => 'javascript:alert(1)']);
            self::fail('The link is accepted.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertStringStartsWith('The "url" of a link is not an address: ', $invalidCourse->getMessage());
        }

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The link "a" names no address.');

        $link->launch($this->activity('link'), $this->enrollment());
    }

    public function testAScormContentIsFramedFromTheContentOrigin(): void
    {
        $scorm = new ScormActivity(new ContentUrls('https://content.example.org/base/'));

        self::assertSame('scorm', $scorm->name());
        self::assertTrue($scorm->completesManually());
        self::assertSame(['path' => 'a/b.html', 'query' => '?x=1', 'scorm_type' => 'sco'], $scorm->settings(['path' => 'a/b.html', 'query' => '?x=1', 'scorm_type' => 'sco']));
        self::assertSame(['path' => 'a.html'], $scorm->settings(['path' => 'a.html']));

        $launch = $scorm->launch($this->activity('scorm', ['path' => 'tools/chisels care.html', 'query' => '?page=2']), $this->enrollment());
        self::assertSame(LaunchWindow::Embedded, $launch->window);
        self::assertSame('https://content.example.org/base/c/tools/chisels%20care.html?page=2', $launch->url);

        foreach ([[], ['path' => 3], ['path' => 'a.html', 'query' => 4]] as $settings) {
            try {
                $scorm->settings($settings);
                self::fail('The settings are accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A SCORM activity names the "path" of its launch file, and its "query" as text.', $invalidCourse->getMessage());
            }
        }

        try {
            $scorm->settings(['path' => '../etc/passwd']);
            self::fail('The path is accepted.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertStringContainsString('".."', $invalidCourse->getMessage());
        }

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The SCORM activity "a" names no launch file.');

        $scorm->launch($this->activity('scorm'), $this->enrollment());
    }

    public function testTheContentOriginBuildsTheAddresses(): void
    {
        $urls = new ContentUrls('https://Content.example.org:8443/files');

        self::assertTrue($urls->isConfigured());
        self::assertSame('https://content.example.org:8443', $urls->origin());
        self::assertSame('https://content.example.org:8443/files/c/a.html', $urls->url(CourseId::of('c'), 'a.html'));
        self::assertSame('https://content.example.org:8443/files/c/a.html', $urls->url(CourseId::of('c'), 'a.html', ''));
        self::assertSame('https://content.example.org:8443/files/c/a.html?x=1', $urls->url(CourseId::of('c'), 'a.html', 'x=1'));
        self::assertSame('https://content.example.org:8443/files/c/a.html#top', $urls->url(CourseId::of('c'), 'a.html', '#top'));
        self::assertSame('http://content.localhost/c/a.html', new ContentUrls('http://content.localhost', true)->url(CourseId::of('c'), 'a.html'));

        $none = new ContentUrls(null);
        self::assertFalse($none->isConfigured());
        self::assertNull($none->origin());

        try {
            new ContentUrls('http://content.localhost');
            self::fail('Localhost is accepted.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertStringStartsWith('The base URL of the content origin is not an address: ', $invalidCourse->getMessage());
        }

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('"phil_courses.content.base_url"');

        $none->url(CourseId::of('c'), 'a.html');
    }

    /**
     * @return iterable<string, array{array<string, bool|float|int|string|null>, string}>
     */
    public static function wrongUnits(): iterable
    {
        yield 'no au' => [['url' => 'https://example.org/'], 'A cmi5 activity names the "au_id" of its unit.'];
        yield 'empty au' => [['au_id' => '', 'url' => 'https://example.org/'], 'A cmi5 activity names the "au_id" of its unit.'];
        yield 'method' => [['au_id' => 'a', 'url' => 'https://example.org/', 'launch_method' => 'Popup'], 'The "launch_method" of a cmi5 activity is AnyWindow or OwnWindow.'];
        yield 'mastery text' => [['au_id' => 'a', 'url' => 'https://example.org/', 'mastery_score' => '0.5'], 'The "mastery_score" of a cmi5 activity is a number from 0 to 1.'];
        yield 'mastery low' => [['au_id' => 'a', 'url' => 'https://example.org/', 'mastery_score' => -0.1], 'The "mastery_score" of a cmi5 activity is a number from 0 to 1.'];
        yield 'mastery high' => [['au_id' => 'a', 'url' => 'https://example.org/', 'mastery_score' => 2], 'The "mastery_score" of a cmi5 activity is a number from 0 to 1.'];
        yield 'nowhere' => [['au_id' => 'a'], 'A cmi5 activity names its "url", or the "path" of its launch file.'];
        yield 'bad url' => [['au_id' => 'a', 'url' => 'ftp://example.org/'], 'The location of a cmi5 activity is wrong: '];
        yield 'bad path' => [['au_id' => 'a', 'path' => '/etc/passwd'], 'The location of a cmi5 activity is wrong: '];
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     */
    #[DataProvider('wrongUnits')]
    public function testAWrongUnitIsRefused(array $settings, string $message): void
    {
        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Store()->types->get('cmi5')->settings($settings);
    }

    public function testAUnitKeepsItsSettings(): void
    {
        $unit = new Store()->types->get('cmi5');

        self::assertSame('cmi5', $unit->name());
        self::assertFalse($unit->completesManually());
        self::assertSame(['au_id' => 'a', 'url' => 'https://example.org/x', 'mastery_score' => 1, 'launch_method' => 'OwnWindow'], $unit->settings(['au_id' => 'a', 'url' => 'https://EXAMPLE.org/x', 'mastery_score' => 1, 'launch_method' => 'OwnWindow']));
        self::assertSame(['au_id' => 'a', 'path' => 'a/b.html', 'mastery_score' => 0], $unit->settings(['au_id' => 'a', 'path' => 'a/b.html', 'mastery_score' => 0]));
        self::assertSame(['au_id' => 'a', 'path' => 'a.html', 'mastery_score' => 0.5], $unit->settings(['au_id' => 'a', 'path' => 'a.html', 'mastery_score' => 0.5]));
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     */
    private function activity(string $type, array $settings = []): Activity
    {
        return new Activity(ActivityId::of('a'), CourseId::of('c'), ModuleId::of('m'), 'A', $type, 0, $settings);
    }

    private function enrollment(): Enrollment
    {
        return new Enrollment(EnrollmentId::of('e'), CourseId::of('c'), LearnerId::of('ada'), new DateTimeImmutable('2026-11-13 10:00 UTC'));
    }
}
