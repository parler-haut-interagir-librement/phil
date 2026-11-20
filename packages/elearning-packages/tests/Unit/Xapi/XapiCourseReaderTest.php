<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Xapi;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Package;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Tests\Fixture\MemoryArchive;
use Phil\ElearningPackages\Xapi\InteractionType;
use Phil\ElearningPackages\Xapi\XapiActivity;
use Phil\ElearningPackages\Xapi\XapiCourse;
use Phil\ElearningPackages\Xapi\XapiCourseReader;
use Phil\ElearningPackages\Xapi\XapiWarning;
use Phil\ElearningPackages\Xapi\XapiWarningKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(XapiCourseReader::class)]
#[CoversClass(XapiCourse::class)]
#[CoversClass(XapiActivity::class)]
#[CoversClass(XapiWarning::class)]
#[CoversClass(InteractionType::class)]
#[CoversClass(XapiWarningKind::class)]
#[CoversClass(PackageReader::class)]
#[CoversClass(Package::class)]
final class XapiCourseReaderTest extends TestCase
{
    public const string TINCAN = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <tincan xmlns="http://projecttincan.com/tincan.xsd">
          <activities>
            <activity id="https://example.org/xapi/quiz-1/q1" type="http://adlnet.gov/expapi/activities/cmi.interaction">
              <name lang="en-US">First question</name>
              <description lang="en-US">Which tool?</description>
              <interactionType>choice</interactionType>
            </activity>
            <activity id="https://example.org/xapi/safety" type="http://adlnet.gov/expapi/activities/course">
              <name lang="en-US">Workshop safety</name>
              <name lang="fr-CA">Sécurité à l'atelier</name>
              <name lang="fr-CA">Doublon</name>
              <name lang="de"></name>
              <description lang="en-US">Tools and gloves.</description>
              <launch lang="en-us">content/index.html?lang=en#top</launch>
              <launch lang="fr">content/index_fr.html</launch>
            </activity>
            <activity id="https://example.org/xapi/quiz-1/q2" type="http://adlnet.gov/expapi/activities/cmi.interaction">
              <name>Second question</name>
              <interactionType>TRUE-FALSE</interactionType>
            </activity>
            <activity id="https://example.org/xapi/video" type="http://adlnet.gov/expapi/activities/media">
              <name lang="en">Video</name>
              <launch>https://Video.Example.org:443/watch/../play?v=1</launch>
            </activity>
          </activities>
        </tincan>
        XML;

    public function testAnXapiPackageIsReadWithItsActivitiesByLanguage(): void
    {
        $course = new XapiCourseReader()->read(self::TINCAN);

        self::assertSame('https://example.org/xapi/safety', $course->id());
        self::assertSame('Workshop safety', $course->title);
        self::assertSame([], $course->warnings);
        self::assertSame(['https://example.org/xapi/quiz-1/q1', 'https://example.org/xapi/safety', 'https://example.org/xapi/quiz-1/q2', 'https://example.org/xapi/video'], array_map(static fn (XapiActivity $activity): string => $activity->id, $course->activities));

        $safety = $course->course;
        self::assertSame(XapiActivity::COURSE, $safety->type);
        self::assertSame(['en-us' => 'Workshop safety', 'fr-ca' => "Sécurité à l'atelier"], $safety->names);
        self::assertSame("Sécurité à l'atelier", $safety->name('fr-CA'));
        self::assertSame("Sécurité à l'atelier", $safety->name('fr'));
        self::assertSame("Sécurité à l'atelier", $safety->name('FR-FR'));
        self::assertSame('Workshop safety', $safety->name('de'));
        self::assertSame('Workshop safety', $safety->name());
        self::assertSame('Tools and gloves.', $safety->description('fr'));
        self::assertSame('content/index.html?lang=en#top', $safety->launch);
        self::assertTrue($safety->isLaunchable());
        self::assertNull($safety->interactionType);

        self::assertSame(['https://example.org/xapi/safety', 'https://example.org/xapi/video'], array_map(static fn (XapiActivity $activity): string => $activity->id, $course->launchable()));
        self::assertSame(['https://example.org/xapi/quiz-1/q1', 'https://example.org/xapi/quiz-1/q2'], array_map(static fn (XapiActivity $activity): string => $activity->id, $course->interactions()));
        self::assertSame(InteractionType::Choice, $course->activities[0]->interactionType);
        self::assertSame(InteractionType::TrueFalse, $course->activity('https://example.org/xapi/quiz-1/q2')?->interactionType);
        self::assertSame(['und' => 'Second question'], $course->activities[2]->names);
        self::assertSame('', $course->activities[2]->description());
        self::assertFalse($course->activities[2]->isLaunchable());
        self::assertNull($course->activity('https://example.org/none'));
        self::assertSame('https://video.example.org/play?v=1', $course->activity('https://example.org/xapi/video')?->launch);
    }

    public function testTheLaunchAndTitleFollowTheLanguageAsked(): void
    {
        $course = new XapiCourseReader(language: 'fr-CA')->read(self::TINCAN);

        self::assertSame("Sécurité à l'atelier", $course->title);
        self::assertSame('content/index_fr.html', $course->course->launch);
    }

    public function testTheFirstLaunchableActivityIsTheCourseWhenNoneIsOfTypeCourse(): void
    {
        $course = new XapiCourseReader()->read(self::tincan('<activity id="urn:x:a"><name>A</name></activity><activity id="urn:x:b" type="http://adlnet.gov/expapi/activities/lesson"><launch>b.html</launch></activity><activity id="urn:x:c"><launch>c.html</launch></activity>'));

        self::assertSame('urn:x:b', $course->id());
        self::assertSame('urn:x:b', $course->title);
        self::assertEquals([new XapiWarning(XapiWarningKind::UnnamedActivity, 'urn:x:b', 'The activity "urn:x:b" has no name: its IRI stands for it.'), new XapiWarning(XapiWarningKind::UnnamedActivity, 'urn:x:c', 'The activity "urn:x:c" has no name: its IRI stands for it.')], $course->warnings);
    }

    public function testRecoverableIssuesAreTypedWarnings(): void
    {
        $course = new XapiCourseReader()->read(self::tincan(
            '<activity id="urn:x:a" type="course"><name>A</name><launch>a.html</launch></activity>'
            .'<activity id="urn:x:a"><name>Again</name></activity>'
            .'<activity id="urn:x:q" type="http://adlnet.gov/expapi/activities/cmi.interaction"><name>Q</name><interactionType>essay</interactionType></activity>'
            .'<activity id="urn:x:r"><name>R</name><interactionType>numeric</interactionType></activity>'
            .'<activity id="urn:x:s"><name lang="not a tag">S</name><interactionType> </interactionType></activity>',
        ));

        self::assertSame([
            XapiWarningKind::InvalidActivityType,
            XapiWarningKind::DuplicateActivity,
            XapiWarningKind::UnknownInteractionType,
            XapiWarningKind::InteractionTypeWithoutInteraction,
        ], array_map(static fn (XapiWarning $warning): XapiWarningKind => $warning->kind, $course->warnings));
        self::assertSame('The type of the activity "urn:x:a" is not an IRI: it is left out.', $course->warnings[0]->message);
        self::assertSame('The activity "urn:x:a" is declared twice: the second is left out.', $course->warnings[1]->message);
        self::assertSame('The interaction type "essay" of the activity "urn:x:q" is not one xAPI defines: it is left out.', $course->warnings[2]->message);
        self::assertSame('The activity "urn:x:r" names an interaction type without being of type cmi.interaction.', $course->warnings[3]->message);
        self::assertSame('urn:x:r', $course->warnings[3]->activity);
        self::assertNull($course->activity('urn:x:a')?->type);
        self::assertSame('A', $course->activity('urn:x:a')?->name());
        self::assertSame(InteractionType::Numeric, $course->activity('urn:x:r')?->interactionType);
        self::assertSame(['und' => 'S'], $course->activity('urn:x:s')?->names);
        self::assertNull($course->activity('urn:x:s')->interactionType);
        self::assertCount(4, $course->activities);
    }

    public function testNamesBeyondTheLanguageBoundAreLeftOut(): void
    {
        $names = '';

        for ($index = 0; $index <= XapiCourseReader::MAX_LANGUAGES; ++$index) {
            $names .= \sprintf('<name lang="x-l%d">N%d</name>', $index, $index);
        }

        $course = new XapiCourseReader()->read(self::tincan('<activity id="urn:x:a">'.$names.'<launch>a.html</launch></activity>'));

        self::assertCount(XapiCourseReader::MAX_LANGUAGES, $course->course->names);
        self::assertSame('N0', $course->title);
        self::assertSame([XapiWarningKind::TooManyLanguages], array_map(static fn (XapiWarning $warning): XapiWarningKind => $warning->kind, $course->warnings));
        self::assertSame('The name of the activity "urn:x:a" is written in more than 32 languages: the others are left out.', $course->warnings[0]->message);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPackages(): iterable
    {
        yield 'another root' => ['<courseStructure/>', 'The xAPI package description has no <tincan> root.'];
        yield 'no activities' => ['<tincan/>', 'The xAPI package description has no <activities>.'];
        yield 'nothing to launch' => [self::tincan('<activity id="urn:x:a"><name>A</name><launch> </launch></activity>'), 'The xAPI package description declares no activity with a <launch>.'];
        yield 'no IRI' => [self::tincan('<activity><launch>a.html</launch></activity>'), 'An activity of the xAPI package description has no IRI of 1 to 2048 bytes.'];
        yield 'not an IRI' => [self::tincan('<activity id="course one"><launch>a.html</launch></activity>'), 'An activity of the xAPI package description has no IRI of 1 to 2048 bytes.'];
        yield 'relative IRI' => [self::tincan('<activity id="course"><launch>a.html</launch></activity>'), 'An activity of the xAPI package description has no IRI of 1 to 2048 bytes.'];
        yield 'IRI too long' => [self::tincan('<activity id="urn:'.str_repeat('a', 2045).'"><launch>a.html</launch></activity>'), 'An activity of the xAPI package description has no IRI of 1 to 2048 bytes.'];
        yield 'launch leaves the package' => [self::tincan('<activity id="urn:x:a"><launch>../secret.html</launch></activity>'), 'The path "../secret.html" holds an empty, "." or ".." segment.'];
        yield 'launch is a script' => [self::tincan('<activity id="urn:x:a"><launch>javascript:alert(1)</launch></activity>'), 'holds a backslash, a colon or a control character'];
        yield 'launch has credentials' => [self::tincan('<activity id="urn:x:a"><launch>https://user:pw@example.org/</launch></activity>'), 'The launch address of the activity "urn:x:a" is refused: '];
        yield 'entity' => ['<!DOCTYPE tincan [<!ENTITY a "b">]><tincan/>', 'declares a document type or an entity'];
    }

    #[DataProvider('invalidPackages')]
    public function testAnInvalidPackageIsRefused(string $xml, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        new XapiCourseReader()->read($xml);
    }

    public function testTheActivitiesAreBounded(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The xAPI package description declares more than 2 activities.');

        new XapiCourseReader(new PackageLimits(items: 2))->read(self::tincan(str_repeat('<activity id="urn:x:a"><launch>a.html</launch></activity>', 3)));
    }

    public function testAnXapiPackageIsReadByThePackageReader(): void
    {
        $package = new PackageReader()->read(new MemoryArchive([
            'tincan.xml' => self::TINCAN,
            'content/index.html' => '<html></html>',
        ]));

        self::assertSame(PackageFormat::Xapi, $package->format);
        self::assertInstanceOf(XapiCourse::class, $package->manifest);
        self::assertSame('https://example.org/xapi/safety', $package->identifier());
        self::assertSame('Workshop safety', $package->title());
        self::assertSame(['tincan.xml', 'content/index.html'], $package->files);
        self::assertSame([], $package->warnings);
    }

    public function testTheReaderNotesMissingLaunchFilesAndTheIssuesSetAside(): void
    {
        $package = new PackageReader(language: 'fr')->read(new MemoryArchive([
            'tincan.xml' => self::tincan('<activity id="urn:x:a" type="http://adlnet.gov/expapi/activities/course"><launch>a.html?x=1</launch></activity><activity id="urn:x:b"><name>B</name><launch>https://example.org/b</launch></activity>'),
        ]));

        self::assertSame('urn:x:a', $package->title());
        self::assertSame([
            'The activity "urn:x:a" has no name: its IRI stands for it.',
            'The launch file "a.html" of the activity "urn:x:a" is not in the package.',
        ], $package->warnings);
    }

    public function testScormAndCmi5ComeBeforeXapi(): void
    {
        $package = new PackageReader()->read(new MemoryArchive([
            'tincan.xml' => self::TINCAN,
            'cmi5.xml' => '<courseStructure><course id="urn:c"><title><langstring>C</langstring></title></course><au id="urn:u"><url>https://example.org/u</url></au></courseStructure>',
        ]));

        self::assertSame(PackageFormat::Cmi5, $package->format);
    }

    private static function tincan(string $activities): string
    {
        return '<tincan xmlns="http://projecttincan.com/tincan.xsd"><activities>'.$activities.'</activities></tincan>';
    }
}
