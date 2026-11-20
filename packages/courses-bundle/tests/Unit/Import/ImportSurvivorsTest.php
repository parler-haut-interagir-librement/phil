<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Import;

use Override;
use Phil\CoursesBundle\Application\Import\Cmi5Planner;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\ImportPlan;
use Phil\CoursesBundle\Application\Import\ImportReport;
use Phil\CoursesBundle\Application\Import\PlannedActivity;
use Phil\CoursesBundle\Application\Import\PlannedModule;
use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Application\Import\ScormPlanner;
use Phil\CoursesBundle\Application\Import\ScormStatus;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Tests\Unit\Fixture\MemoryArchive;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The details of the plans, of the import and of the imported results that the scenarios of the other tests do not
 * reach.
 */
#[CoversClass(ScormPlanner::class)]
#[CoversClass(Cmi5Planner::class)]
#[CoversClass(CourseImporter::class)]
#[CoversClass(ResultImporter::class)]
#[CoversClass(ScormStatus::class)]
#[CoversClass(ImportReport::class)]
#[CoversClass(PlannedActivity::class)]
final class ImportSurvivorsTest extends TestCase
{
    private const string SEQUENCING = 'The sequencing and navigation rules of SCORM 2004 are kept for information: the course does not enforce them.';

    public function testEachSequencingIsNotedWhereverItIs(): void
    {
        $item = '<item identifier="a" identifierref="r"/>';

        foreach ([
            'organization' => $this->manifest($item.'<sequencing/>'),
            'collection' => $this->manifest($item, '<sequencingCollection/>'),
            'item' => $this->manifest('<item identifier="a" identifierref="r"><sequencing/></item>'),
        ] as $where => $xml) {
            self::assertContains(self::SEQUENCING, $this->scorm($xml)->warnings, $where);
        }

        self::assertNotContains(self::SEQUENCING, $this->scorm($this->manifest($item))->warnings);
    }

    public function testTheItemsAfterOnesLeftOutArePlanned(): void
    {
        $plan = $this->scorm($this->manifest('<item identifier="h" identifierref="r" isvisible="false"/><item identifier="n"/><item identifier="a" identifierref="r"><title>A</title></item>'));

        self::assertContains('The hidden item "h" is left out.', $plan->warnings);
        self::assertSame(['A'], $this->titles($plan->modules[0]));
    }

    public function testTheTitlesOfNestedItemsArePrefixedAndEveryLeafKept(): void
    {
        $plan = $this->scorm($this->manifest('<item identifier="m"><title>M</title><item identifier="x" identifierref="r"><title>X</title></item><item identifier="b"><title>B</title><item identifier="c"><title>C</title><item identifier="d" identifierref="r"><title>D</title></item><item identifier="e" identifierref="r"><title>E</title></item></item></item></item>'));

        self::assertSame(['X', 'B / C / D', 'B / C / E'], $this->titles($plan->modules[0]));
    }

    public function testAnItemWithAPassingScoreIsSatisfiedOncePassed(): void
    {
        self::assertSame(CompletionRequirement::CompletedOrPassed, $this->scorm($this->manifest('<item identifier="a" identifierref="r"><completionThreshold>0.5</completionThreshold></item>'))->modules[0]->activities[0]->requirement);
        self::assertSame(CompletionRequirement::Passed, $this->scorm($this->manifest('<item identifier="a" identifierref="r"><sequencing><objectives><primaryObjective satisfiedByMeasure="true"><minNormalizedMeasure>0.7</minNormalizedMeasure></primaryObjective></objectives></sequencing></item>'))->modules[0]->activities[0]->requirement);
    }

    public function testTheSettingsOfAScormActivityAreBounded(): void
    {
        $sequencing = '<sequencing>'.str_repeat('x', 16_384 - 25).'</sequencing>';
        $plan = $this->scorm($this->manifest('<item identifier="a" identifierref="q"><prerequisites>'.str_repeat('é', 1100).'</prerequisites>'.$sequencing.'</item>'));
        $settings = $plan->modules[0]->activities[0]->settings;

        self::assertSame(str_repeat('é', 1000), $settings['prerequisites']);
        self::assertSame($sequencing, $settings['sequencing']);
        self::assertSame('?y=2', $settings['query']);
    }

    public function testTheUnitsOfNestedBlocksArePlannedWithTheirPath(): void
    {
        $course = new Cmi5CourseReader()->read('<courseStructure><course id="c"/><block id="b"><title><langstring>B</langstring></title>'
            .'<au id="a1"><title><langstring>One</langstring></title><url>a.html</url></au><au id="a2"><title><langstring>Two</langstring></title><url>a.html</url></au>'
            .'<block id="b2"><title><langstring>Inner</langstring></title><au id="a3"><title><langstring>Three</langstring></title><url>a.html</url></au>'
            .'<block id="b3"><title><langstring>Deep</langstring></title><au id="a4"><title><langstring>Four</langstring></title><url>a.html</url></au><au id="a5"><title><langstring>Five</langstring></title><url>a.html</url></au></block></block></block></courseStructure>');
        $plan = new Cmi5Planner()->plan($course);

        self::assertSame(['One', 'Two', 'Inner / Three', 'Inner / Deep / Four', 'Inner / Deep / Five'], $this->titles($plan->modules[0]));
        self::assertSame('a.html', $plan->modules[0]->activities[0]->settings['path']);
    }

    public function testAStructureAloneShowsTheFileItNamesShort(): void
    {
        $course = new Cmi5CourseReader()->read('<courseStructure><course id="c"/><au id="a"><url>'.str_repeat('é', 130).'</url></au></courseStructure>');

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('names a file, "'.str_repeat('é', 120).'", of a package that is not given');

        new Cmi5Planner()->plan($course, false);
    }

    public function testTheArchiveIsClosedAndTheActivitiesRequiredWithoutADispatcher(): void
    {
        $store = new Store();
        $archive = new MemoryArchive(Packages::cmi5Files());
        $opener = new readonly class($archive) implements PackageOpener {
            public function __construct(
                private PackageArchive $archive,
            ) {
            }

            #[Override]
            public function open(string $path, PackageLimits $limits): PackageArchive
            {
                return $this->archive;
            }
        };
        $importer = new CourseImporter($opener, $store->courses, $store->storage);

        $report = $importer->import('course.zip');

        self::assertTrue($archive->closed);
        // The unit whose moveOn is NotApplicable is not required: cmi5 counts it satisfied.
        self::assertSame([false, true, true], array_map(static fn (Activity $activity): bool => $activity->required, $store->courses->outline($report->course)->activities()));
        self::assertSame([], $store->events->events);

        $refused = new MemoryArchive(['index.html' => '']);
        $failing = new CourseImporter(new readonly class($refused) implements PackageOpener {
            public function __construct(
                private PackageArchive $archive,
            ) {
            }

            #[Override]
            public function open(string $path, PackageLimits $limits): PackageArchive
            {
                return $this->archive;
            }
        }, $store->courses, $store->storage);

        try {
            $failing->import('other.zip');
            self::fail('The package is accepted.');
        } catch (PackageRefused) {
            self::assertTrue($refused->closed);
        }
    }

    public function testAStructureAloneNamesAbsoluteUrlsOnly(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('of a package that is not given');

        new Store()->importer->structure('<courseStructure><course id="c"/><au id="a"><url>a.html</url></au></courseStructure>');
    }

    public function testTheValuesOfAResultAreTrimmedAndShownShort(): void
    {
        $store = new Store();
        $course = $store->importer->import(Packages::zip(Packages::scorm12Files()))->course;

        $result = $store->results->import($course, [
            2 => ['learner' => ' ada ', 'item' => 'intro', 'status' => 'completed', 'date' => ' 2025-06-02 '],
            3 => ['learner' => 'bob', 'item' => str_repeat('é', 130), 'status' => 'completed'],
            4 => ['learner' => 'bob', 'item' => 'intro', 'status' => 'completed', 'date' => str_repeat('é', 50)],
        ]);

        self::assertSame(1, $result['imported']);
        self::assertNotNull($store->enrollments->of($course, LearnerId::of('ada')));
        self::assertSame('No activity of the course comes from the item "'.str_repeat('é', 120).'".', $result['refused'][3]);
        self::assertSame('"'.str_repeat('é', 40).'" is not a moment in ISO 8601.', $result['refused'][4]);
    }

    public function testAStatusAndAScoreAreReadWhateverTheirSpaces(): void
    {
        self::assertNull(ScormStatus::outcome('passed', '   ')->score);

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 40).'" is not a status SCORM writes.');

        ScormStatus::outcome(str_repeat('é', 50));
    }

    private function manifest(string $items, string $after = ''): string
    {
        return '<manifest identifier="m"><metadata><schemaversion>2004 4th Edition</schemaversion></metadata><organizations><organization identifier="o"><title>O</title>'.$items.'</organization></organizations>'
            .'<resources><resource identifier="r" href="a.html"/><resource identifier="q" href="a.html?y=2"/></resources>'.$after.'</manifest>';
    }

    private function scorm(string $xml): ImportPlan
    {
        return new ScormPlanner()->plan(new ScormManifestReader()->read($xml));
    }

    /**
     * @return list<string>
     */
    private function titles(PlannedModule $module): array
    {
        return array_map(static fn (PlannedActivity $activity): string => $activity->title, $module->activities);
    }

    public function testTheDefaultsOfAReportAndOfAPlannedActivity(): void
    {
        $report = new ImportReport(CourseId::generate(), CourseOrigin::Cmi5, 1, 2, 3, []);
        self::assertSame(0, $report->blocks);

        $activity = new PlannedActivity('Unit', 'page', [], CompletionRequirement::Completed, 'unit');
        self::assertTrue($activity->required);
        self::assertNull($activity->block);
    }
}
