<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Import;

use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Import\Cmi5Planner;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\ImportPlan;
use Phil\CoursesBundle\Application\Import\ImportReport;
use Phil\CoursesBundle\Application\Import\PlannedActivity;
use Phil\CoursesBundle\Application\Import\PlannedBlock;
use Phil\CoursesBundle\Application\Import\PlannedModule;
use Phil\CoursesBundle\Application\Import\ScormPlanner;
use Phil\CoursesBundle\Application\ModuleOutline;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Event\CourseImported;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Tests\Unit\Fixture\MemoryArchive;
use Phil\CoursesBundle\Tests\Unit\Fixture\MemoryStorage;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormManifest;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ScormPlanner::class)]
#[CoversClass(Cmi5Planner::class)]
#[CoversClass(CourseImporter::class)]
#[CoversClass(ImportPlan::class)]
#[CoversClass(ImportReport::class)]
#[CoversClass(PlannedModule::class)]
#[CoversClass(PlannedActivity::class)]
final class ImportTest extends TestCase
{
    public function testAScorm12PackageBecomesACourse(): void
    {
        $plan = $this->scorm(new MemoryArchive(Packages::scorm12Files()));

        self::assertSame('Workshop safety', $plan->title);
        self::assertSame('', $plan->description);
        self::assertSame(PackageFormat::Scorm12, $plan->format);
        self::assertSame('com.example.safety', $plan->source);
        self::assertSame(['Workshop safety', 'Hand tools'], array_map(static fn (PlannedModule $module): string => $module->title, $plan->modules));
        self::assertSame(['Welcome', 'Further reading'], array_map(static fn (PlannedActivity $activity): string => $activity->title, $plan->modules[0]->activities));
        self::assertSame(['Saws', 'Chisels / Care'], array_map(static fn (PlannedActivity $activity): string => $activity->title, $plan->modules[1]->activities));
        self::assertSame(4, $plan->activities());

        [$welcome, $reading] = $plan->modules[0]->activities;
        self::assertSame('scorm', $welcome->type);
        self::assertSame(['path' => 'content/intro/index.html', 'scorm_type' => 'sco', 'scorm_version' => 'scorm12'], $welcome->settings);
        self::assertSame('intro', $welcome->source);
        self::assertSame(CompletionRequirement::CompletedOrPassed, $welcome->requirement);
        self::assertSame('link', $reading->type);
        self::assertSame(['url' => 'https://example.org/reading'], $reading->settings);

        $saws = $plan->modules[1]->activities[0];
        self::assertSame(['path' => 'content/tools/saws.html', 'query' => '?lang=en&page=2', 'scorm_type' => 'sco', 'scorm_version' => 'scorm12', 'mastery_score' => 0.8, 'prerequisites' => 'intro'], $saws->settings);
        self::assertSame('content/tools/chisels care.html', $plan->modules[1]->activities[1]->settings['path']);
        self::assertSame('asset', $plan->modules[1]->activities[1]->settings['scorm_type']);

        self::assertSame([
            'The prerequisites of SCORM 1.2 are kept for information: impose the order of the course to enforce one.',
            'The hidden item "hidden" is left out.',
            'The item "empty" launches no resource with a file: it is left out.',
            'A SCORM package carries no learner results: results exported from the previous platform are imported with "phil:courses:import-results".',
        ], $plan->warnings);
    }

    public function testAScorm2004PackageKeepsItsSequencingForInformation(): void
    {
        $plan = $this->scorm(new MemoryArchive(['imsmanifest.xml' => Packages::SCORM2004]));

        self::assertSame('First aid', $plan->title);
        self::assertSame(['Basics'], array_map(static fn (PlannedModule $module): string => $module->title, $plan->modules));
        $activity = $plan->modules[0]->activities[0];
        self::assertSame('scorm2004', $activity->settings['scorm_version']);
        self::assertSame(0.75, $activity->settings['completion_threshold']);
        self::assertStringContainsString('controlMode', (string) $activity->settings['sequencing']);
        self::assertSame([
            'The launch file "sco/start.html" of the item "u1" is not in the package.',
            'The launch file "sco/start.html" of the item "a1" is not in the package.',
            'The manifest has 2 organizations: the import reads "second" and leaves the others out.',
            'The sequencing and navigation rules of SCORM 2004 are kept for information: the course does not enforce them.',
            'A SCORM package carries no learner results: results exported from the previous platform are imported with "phil:courses:import-results".',
        ], $plan->warnings);
    }

    public function testParametersAreAppendedAsContentPackagingSays(): void
    {
        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"><title>O</title>'
            .'<item identifier="a" identifierref="r1" parameters="#frag"/><item identifier="b" identifierref="r2" parameters="x=1"/><item identifier="c" identifierref="r2"/>'
            .'<item identifier="d"><title>Hidden branch</title><item identifier="e" identifierref="r1" isvisible="false"/><item identifier="f" identifierref="missing"/></item>'
            .'</organization></organizations><resources><resource identifier="r1" href="a.html?y=2"/><resource identifier="r2" href="b.html"/></resources></manifest>';
        $plan = $this->scorm(new MemoryArchive(['imsmanifest.xml' => $xml, 'a.html' => '', 'b.html' => '']));

        self::assertSame(['?y=2#frag', 'x=1', null], array_map(static fn (PlannedActivity $activity): mixed => $activity->settings['query'] ?? null, $plan->modules[0]->activities));
        self::assertSame(['O'], array_map(static fn (PlannedModule $module): string => $module->title, $plan->modules));
        self::assertContains('The hidden item "e" is left out.', $plan->warnings);
        self::assertContains('The item "f" launches no resource with a file: it is left out.', $plan->warnings);
    }

    public function testAPackageWithoutAnActivityIsRefused(): void
    {
        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"><item identifier="a" isvisible="false"/><item identifier="b"/></organization></organizations></manifest>';

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The package describes no activity the import can create.');

        new ScormPlanner()->plan(new ScormManifestReader()->read($xml));
    }

    public function testACmi5StructureBecomesACourse(): void
    {
        $plan = $this->cmi5(new MemoryArchive(Packages::cmi5Files()));

        self::assertSame('Navigation', $plan->title);
        self::assertSame('Reading a chart.', $plan->description);
        self::assertSame(PackageFormat::Cmi5, $plan->format);
        self::assertSame(['Navigation', 'Charts'], array_map(static fn (PlannedModule $module): string => $module->title, $plan->modules));
        self::assertSame(['Symbols', 'Tides / Tide tables'], array_map(static fn (PlannedActivity $activity): string => $activity->title, $plan->modules[1]->activities));

        $welcome = $plan->modules[0]->activities[0];
        self::assertSame('cmi5', $welcome->type);
        self::assertSame(['path' => 'welcome/index.html', 'query' => '?mode=short', 'au_id' => 'https://example.org/aus/welcome', 'launch_method' => 'AnyWindow'], $welcome->settings);
        self::assertSame(CompletionRequirement::NotApplicable, $welcome->requirement);

        $symbols = $plan->modules[1]->activities[0];
        self::assertSame(['url' => 'https://content.example.net/symbols/', 'au_id' => 'https://example.org/aus/symbols', 'launch_method' => 'OwnWindow', 'mastery_score' => 0.8, 'launch_parameters' => '{"level":2}', 'entitlement_key' => 'key-123', 'activity_type' => 'http://adlnet.gov/expapi/activities/lesson'], $symbols->settings);
        self::assertSame(CompletionRequirement::CompletedAndPassed, $symbols->requirement);
        self::assertSame('https://example.org/aus/symbols', $symbols->source);

        // The tree of the blocks: Charts after the unit Welcome at the first level, Tides after Symbols inside Charts.
        self::assertEquals([
            new PlannedBlock('Charts', 'https://example.org/blocks/charts', 1),
            new PlannedBlock('Tides', 'https://example.org/blocks/tides', 1, 0),
        ], $plan->blocks);
        self::assertSame([null, 0, 1], [$welcome->block, $symbols->block, $plan->modules[1]->activities[1]->block]);
        self::assertSame([false, true, true], [$welcome->required, $symbols->required, $plan->modules[1]->activities[1]->required]);
        self::assertSame(['The entitlement keys of the assignable units are kept and handed to them, not checked.', 'The objectives of the course structure are left out.'], $plan->warnings);
    }

    public function testACmi5UnitNamesAFileOfThePackageOrAnAddress(): void
    {
        $missing = $this->cmi5(new MemoryArchive(['cmi5.xml' => '<courseStructure><course id="c"/><au id="a"><url>a.html</url></au><au id="b"><url>b.html</url></au></courseStructure>', 'a.html' => '']));
        self::assertSame(['The launch file "b.html" of the assignable unit "b" is not in the package.'], $missing->warnings);
        self::assertSame(['c'], array_map(static fn (PlannedModule $module): string => $module->title, $missing->modules));
        self::assertCount(2, $missing->modules[0]->activities);
        self::assertSame([], new Cmi5Planner()->plan(new Cmi5CourseReader()->read('<courseStructure><course id="c"/><block id="b"/><au id="a"><url>https://example.org/a</url></au></courseStructure>'), false)->warnings);

        foreach ([
            'names a file, "a.html", of a package that is not given' => ['<courseStructure><course id="c"/><au id="a"><url>a.html</url></au></courseStructure>', false],
            'The URL of the assignable unit "a" is not an address: ' => ['<courseStructure><course id="c"/><au id="a"><url>https://localhost/a</url></au></courseStructure>', false],
            'holds an empty, "." or ".." segment' => ['<courseStructure><course id="c"/><au id="a"><url>../a.html</url></au></courseStructure>', true],
        ] as $message => [$xml, $packaged]) {
            try {
                new Cmi5Planner()->plan(new Cmi5CourseReader()->read($xml), $packaged);
                self::fail('The unit is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertStringContainsString($message, $packageRefused->getMessage());
            }
        }
    }

    public function testATitleIsOneBoundedLine(): void
    {
        self::assertSame('A b c', ImportPlan::title("  A \n b\t c ", 'f'));
        self::assertSame('f', ImportPlan::title(' ', 'f'));
        self::assertSame(str_repeat('é', 255), ImportPlan::title(str_repeat('é', 300), 'f'));
    }

    public function testAScormArchiveIsImportedWithItsFiles(): void
    {
        $store = new Store();
        $report = $store->importer->import(Packages::zip(Packages::scorm12Files()));

        self::assertSame(PackageFormat::Scorm12, $report->format);
        self::assertSame([2, 4, 5], [$report->modules, $report->activities, $report->files]);
        self::assertCount(4, $report->warnings);
        self::assertSame([CourseImported::class], array_map(static fn (object $event): string => $event::class, $store->events->events));
        self::assertSame([$report->course->value, 'scorm12'], [$store->events->of(CourseImported::class)[0]->course, $store->events->of(CourseImported::class)[0]->format]);

        $course = $store->courses->get($report->course);
        self::assertSame('Workshop safety', $course->title);
        self::assertSame(CourseOrigin::Scorm12, $course->origin);
        self::assertSame('com.example.safety', $course->source);
        self::assertFalse($course->ordered);
        self::assertSame('<h1>Saws</h1>', $store->storage->read($report->course->value.'/content/tools/saws.html'));
        self::assertTrue($store->storage->exists($report->course->value.'/imsmanifest.xml'));
        self::assertSame(['intro', 'outro', 'saws', 'chisel-care'], array_map(static fn (Activity $activity): ?string => $activity->source, $store->courses->outline($report->course)->activities()));
        self::assertSame('Renamed', $store->courses->get($store->importer->import(Packages::zip(Packages::scorm12Files()), 'Renamed')->course)->title);
    }

    public function testACmi5ArchiveOrStructureIsImported(): void
    {
        $store = new Store();
        $report = $store->importer->import(Packages::zip(Packages::cmi5Files()));

        self::assertSame(PackageFormat::Cmi5, $report->format);
        self::assertSame([2, 2, 3, 3], [$report->modules, $report->blocks, $report->activities, $report->files]);
        $course = $store->courses->get($report->course);
        $blocks = $store->courses->blocks($report->course);
        self::assertSame([['Charts', 'https://example.org/blocks/charts', 1, null], ['Tides', 'https://example.org/blocks/tides', 1, $blocks[0]->id->value]], array_map(static fn (Block $block): array => [$block->title, $block->source, $block->position, $block->parent?->value], $blocks));
        self::assertSame([null, $blocks[0]->id->value, $blocks[1]->id->value], array_map(static fn (Activity $activity): ?string => $activity->block?->value, $store->courses->outline($report->course)->activities()));
        self::assertSame("Navigation à l'estime", $course->title);
        self::assertSame('Reading a chart.', $course->description);
        self::assertSame(CourseOrigin::Cmi5, $course->origin);
        self::assertSame(['NotApplicable', 'CompletedAndPassed', 'Completed'], array_map(static fn (Activity $activity): string => $activity->requirement->value, $store->courses->outline($report->course)->activities()));

        $file = sys_get_temp_dir().'/phil-courses-structure-'.bin2hex(random_bytes(4)).'.XML';
        file_put_contents($file, '<courseStructure><course id="c"><title><langstring>Alone</langstring></title></course><au id="a"><url>https://example.org/a</url></au></courseStructure>');
        $alone = $store->importer->import($file);
        self::assertSame([0, 0], [$alone->files, $alone->blocks]);
        self::assertSame([], $store->courses->blocks($alone->course));
        self::assertSame('Alone', $store->courses->get($alone->course)->title);
        self::assertSame(['Alone'], array_map(static fn (ModuleOutline $module): string => $module->module->title, $store->courses->outline($alone->course)->modules));
        self::assertSame('Given', $store->courses->get($store->importer->structure('<courseStructure><course id="c"/><au id="a"><url>https://example.org/a</url></au></courseStructure>', 'Given')->course)->title);
    }

    public function testAStructureFileIsBounded(): void
    {
        $store = new Store(limits: new PackageLimits(manifestBytes: 10));
        $file = sys_get_temp_dir().'/phil-courses-large-'.bin2hex(random_bytes(4)).'.xml';
        file_put_contents($file, str_repeat('x', 11));

        foreach ([$file, sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(4)).'.xml'] as $path) {
            try {
                $store->importer->import($path);
                self::fail('The file is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertSame('The course structure is not a readable file of 10 bytes at most.', $packageRefused->getMessage());
            }
        }

        file_put_contents($file, str_repeat('x', 10));
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('not well-formed');
        $store->importer->import($file);
    }

    public function testAnArchiveWithoutAManifestIsRefusedAndClosed(): void
    {
        $store = new Store();

        try {
            $store->importer->import(Packages::zip(['index.html' => '']));
            self::fail('The archive is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The package holds neither cmi5.xml nor imsmanifest.xml at its root.', $packageRefused->getMessage());
        }

        self::assertSame([], $store->courses->all());
    }

    public function testAnImportThatFailsHalfWayRemovesWhatItWrote(): void
    {
        $store = new Store(limits: new PackageLimits(totalBytes: 4000));
        $files = Packages::scorm12Files();
        $files['content/zz-large.bin'] = str_repeat('a', 4000);

        try {
            $store->importer->import(Packages::zip($files));
            self::fail('The archive is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertStringContainsString('uncompresses to more than 4000 bytes', $packageRefused->getMessage());
        }

        self::assertSame([], $store->courses->all());
        self::assertSame([], $store->storage->files);
    }

    public function testACourseThatCannotBeCreatedIsRefusedAndRemoved(): void
    {
        $store = new Store();
        $broken = new CourseImporter(new ZipPackageOpener(), new Courses($store->courseStore, new ActivityTypes([]), $store->clock), $store->storage);

        try {
            $broken->import(Packages::zip(Packages::cmi5Files()));
            self::fail('The import succeeds without the types.');
        } catch (PackageRefused $packageRefused) {
            self::assertStringStartsWith('No activity type is named "cmi5"', $packageRefused->getMessage());
        }

        self::assertSame([], $store->courses->all());
        self::assertSame([], $store->storage->files);

        $full = new MemoryStorage();
        $full->full = true;

        $failing = new CourseImporter(new ZipPackageOpener(), $store->courses, $full);

        try {
            $failing->import(Packages::zip(Packages::cmi5Files()));
            self::fail('The import succeeds without storage.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The disk is full.', $runtimeException->getMessage());
        }

        self::assertSame([], $store->courses->all());
    }

    private function scorm(MemoryArchive $archive): ImportPlan
    {
        $package = new PackageReader()->read($archive);
        self::assertInstanceOf(ScormManifest::class, $package->manifest);

        return new ScormPlanner()->plan($package->manifest, $package->warnings);
    }

    private function cmi5(MemoryArchive $archive): ImportPlan
    {
        $package = new PackageReader()->read($archive);
        self::assertInstanceOf(Cmi5Course::class, $package->manifest);

        return new Cmi5Planner()->plan($package->manifest, true, $package->warnings);
    }
}
