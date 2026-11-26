<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Import;

use Override;
use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Application\Contract\XapiPackageReader;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\XapiLaunchable;
use Phil\CoursesBundle\Application\Import\XapiPackage;
use Phil\CoursesBundle\Application\Import\XapiPlanner;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Infrastructure\Import\ElearningXapiPackageReader;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An xAPI package: recognised by its `tincan.xml` alone, read by the reader the application configures, refused
 * without one, and turned into a course of one module of xAPI activities.
 */
#[CoversClass(XapiPlanner::class)]
#[CoversClass(XapiPackage::class)]
#[CoversClass(XapiLaunchable::class)]
#[CoversClass(CourseImporter::class)]
#[CoversClass(CourseOrigin::class)]
#[CoversClass(ElearningXapiPackageReader::class)]
final class XapiImportTest extends TestCase
{
    public function testThePlanHoldsOneModuleOfXapiActivities(): void
    {
        $plan = new XapiPlanner()->plan(new XapiPackage('https://example.org/xapi/sailing', '  Sailing   basics ', 'Knots and sails.', [
            new XapiLaunchable('https://example.org/xapi/knots', 'Knots', 'knots/index.html?lang=en', 0.8),
            new XapiLaunchable('https://example.org/xapi/sails', '', 'https://units.example.net/sails/'),
        ], ['A note.']));

        self::assertSame(['Sailing basics', 'Knots and sails.', CourseOrigin::Xapi, 'https://example.org/xapi/sailing', ['A note.']], [$plan->title, $plan->description, $plan->format, $plan->source, $plan->warnings]);
        self::assertCount(1, $plan->modules);
        self::assertSame('Sailing basics', $plan->modules[0]->title);
        [$knots, $sails] = $plan->modules[0]->activities;
        self::assertSame(['Knots', XapiActivity::NAME, ['path' => 'knots/index.html', 'query' => '?lang=en', 'activity_id' => 'https://example.org/xapi/knots', 'mastery_score' => 0.8], CompletionRequirement::Passed, 'https://example.org/xapi/knots', true], [$knots->title, $knots->type, $knots->settings, $knots->requirement, $knots->source, $knots->required]);
        self::assertSame(['https://example.org/xapi/sails', ['url' => 'https://units.example.net/sails/', 'activity_id' => 'https://example.org/xapi/sails'], CompletionRequirement::Completed], [$sails->title, $sails->settings, $sails->requirement]);
    }

    public function testALaunchOutsideThePackageOrNoActivityIsRefused(): void
    {
        foreach (['../escape.html', 'https://'] as $launch) {
            try {
                new XapiPlanner()->plan(new XapiPackage('urn:c', 'C', '', [new XapiLaunchable('urn:a', 'A', $launch)]));
                self::fail('Planned: '.$launch);
            } catch (PackageRefused $packageRefused) {
                self::assertStringStartsWith('The launch of the xAPI activity "urn:a" is neither a file of the package nor an address: ', $packageRefused->getMessage());
            }
        }

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The package describes no activity the import can create.');
        new XapiPlanner()->plan(new XapiPackage('urn:c', 'C', '', []));
    }

    public function testTheIdentifierOfARefusedActivityIsCutAt120Characters(): void
    {
        $id = str_repeat('é', 130);

        try {
            new XapiPlanner()->plan(new XapiPackage('urn:c', 'C', '', [new XapiLaunchable($id, 'A', '../escape.html')]));
            self::fail('Planned.');
        } catch (PackageRefused $packageRefused) {
            self::assertStringStartsWith('The launch of the xAPI activity "'.str_repeat('é', 120).'" is neither', $packageRefused->getMessage());
        }
    }

    public function testAnXapiPackageIsRefusedWithoutAReader(): void
    {
        $store = new Store();

        try {
            $store->importer->import(Packages::zip(['tincan.xml' => '<tincan/>', 'index.html' => 'x']));
            self::fail('Imported.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The package is an xAPI package, described by tincan.xml: it is imported once a reader of xAPI packages is configured in "phil_courses.import.xapi_reader".', $packageRefused->getMessage());
        }

        self::assertSame([], $store->courses->all());
    }

    public function testAnXapiPackageIsImportedThroughTheReader(): void
    {
        $store = new Store();
        $reader = new class implements XapiPackageReader {
            public int $reads = 0;

            #[Override]
            public function read(PackageArchive $archive): XapiPackage
            {
                ++$this->reads;

                return new XapiPackage('urn:sailing', 'Sailing', '', [new XapiLaunchable('urn:knots', 'Knots', $archive->has('knots.html') ? 'knots.html' : 'missing.html')]);
            }
        };
        $importer = new CourseImporter(new ZipPackageOpener(), $store->courses, $store->storage, events: $store->events, xapi: $reader);

        $report = $importer->import(Packages::zip(['tincan.xml' => '<tincan/>', 'knots.html' => 'x']));

        self::assertSame([CourseOrigin::Xapi, 1, 1, 2], [$report->format, $report->modules, $report->activities, $report->files]);
        $course = $store->courses->get($report->course);
        self::assertSame([CourseOrigin::Xapi, 'urn:sailing'], [$course->origin, $course->source]);
        $activity = $store->courseStore->activities($course->id)[0];
        self::assertSame([XapiActivity::NAME, 'urn:knots', 'knots.html'], [$activity->type, $activity->stringSetting('activity_id'), $activity->stringSetting('path')]);

        // A package that holds a SCORM or cmi5 manifest as well is read as that package.
        $scorm = $importer->import(Packages::zip(['tincan.xml' => '<tincan/>'] + Packages::scorm12Files()));
        self::assertSame(CourseOrigin::Scorm12, $scorm->format);
        $cmi5 = $importer->import(Packages::zip(['tincan.xml' => '<tincan/>'] + Packages::cmi5Files()));
        self::assertSame(CourseOrigin::Cmi5, $cmi5->format);
        self::assertSame(1, $reader->reads);
    }

    public function testTheReaderOfTheLibraryReadsTheDescriptionOfThePackage(): void
    {
        $store = new Store();
        $tincan = <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <tincan xmlns="http://projecttincan.com/tincan.xsd">
                <activities>
                    <activity id="https://example.org/xapi/knots" type="http://adlnet.gov/expapi/activities/lesson">
                        <name lang="en">Knots</name><name lang="fr">Nœuds</name>
                        <launch lang="en">knots/index.html?step=1</launch>
                    </activity>
                    <activity id="https://example.org/xapi/sailing" type="http://adlnet.gov/expapi/activities/course">
                        <name lang="en">Sailing</name><name lang="fr">Voile</name>
                        <description lang="en">Knots and sails.</description><description lang="fr">Nœuds et voiles.</description>
                        <launch lang="en">index.html</launch>
                    </activity>
                    <activity id="https://example.org/xapi/q1" type="http://adlnet.gov/expapi/activities/cmi.interaction">
                        <name lang="en">Question</name>
                        <interactionType>sorcery</interactionType>
                    </activity>
                    <activity id="https://example.org/xapi/q2" type="http://adlnet.gov/expapi/activities/cmi.interaction">
                        <name lang="en">Knot</name>
                        <interactionType>choice</interactionType>
                        <correctResponsePatterns><correctResponsePattern>bowline</correctResponsePattern></correctResponsePatterns>
                        <choices><component><id>reef</id></component></choices>
                    </activity>
                    <activity id="https://example.org/xapi/knots" type="http://adlnet.gov/expapi/activities/lesson">
                        <launch lang="en">again.html</launch>
                    </activity>
                </activities>
            </tincan>
            XML;
        $importer = new CourseImporter(new ZipPackageOpener(), $store->courses, $store->storage, events: $store->events, xapi: new ElearningXapiPackageReader(new PackageLimits(), 'fr'));

        $report = $importer->import(Packages::zip(['tincan.xml' => $tincan, 'index.html' => 'x', 'knots/index.html' => 'y']));
        $course = $store->courses->get($report->course);
        self::assertSame(['Voile', 'Nœuds et voiles.', 'https://example.org/xapi/sailing', CourseOrigin::Xapi], [$course->title, $course->description, $course->source, $course->origin]);
        self::assertSame([
            ['Nœuds', 'https://example.org/xapi/knots', 'knots/index.html', '?step=1'],
            ['Voile', 'https://example.org/xapi/sailing', 'index.html', null],
        ], array_map(static fn (Activity $activity): array => [$activity->title, $activity->stringSetting('activity_id'), $activity->stringSetting('path'), $activity->setting('query')], $store->courseStore->activities($course->id)));
        self::assertCount(4, $report->warnings, implode("\n", $report->warnings));
        self::assertStringContainsString('declared twice', implode(' ', $report->warnings));
        self::assertContains('A correct response of the activity "https://example.org/xapi/q2" names "bowline", which its choices do not declare: the response is kept.', $report->warnings);

        $english = new ElearningXapiPackageReader()->read(new ZipPackageOpener()->open(Packages::zip(['tincan.xml' => $tincan]), new PackageLimits()));
        self::assertSame(['Sailing', 'Knots and sails.', 'Knots', 'knots/index.html?step=1', null], [$english->title, $english->description, $english->activities[0]->title, $english->activities[0]->launch, $english->activities[0]->masteryScore]);

        foreach (['<tincan/>' => 'The xAPI package description has no <activities>.', '<other/>' => 'The xAPI package description has no <tincan> root.'] as $xml => $message) {
            try {
                $importer->import(Packages::zip(['tincan.xml' => $xml]));
                self::fail('Imported.');
            } catch (PackageRefused $packageRefused) {
                self::assertSame($message, $packageRefused->getMessage());
                self::assertInstanceOf(UnreadablePackage::class, $packageRefused->getPrevious());
            }
        }
    }

    public function testTheOriginOfAFormatHasItsValue(): void
    {
        self::assertSame([CourseOrigin::Scorm12, CourseOrigin::Scorm2004, CourseOrigin::Cmi5, CourseOrigin::Xapi], array_map(CourseOrigin::of(...), PackageFormat::cases()));
    }
}
