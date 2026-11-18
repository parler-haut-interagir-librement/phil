<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Import;

use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Application\Import\ScormStatus;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScormStatus::class)]
#[CoversClass(ResultImporter::class)]
final class ResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, bool, ?bool, ?float}>
     */
    public static function statuses(): iterable
    {
        yield 'passed' => ['passed', '85', false, true, 0.85];
        yield 'failed' => ['Failed', '40.5', false, false, 0.405];
        yield 'completed' => ['completed', null, true, null, null];
        yield 'incomplete' => ['incomplete', '', false, null, null];
        yield 'browsed' => ['browsed', null, false, null, null];
        yield 'not attempted' => ['not attempted', null, false, null, null];
        yield 'not_attempted' => ['not_attempted', null, false, null, null];
        yield 'unknown' => ['unknown', null, false, null, null];
        yield '2004 pair' => ['completed+passed', '100', true, true, 1.0];
        yield '2004 failed' => [' completed + failed ', '0', true, false, 0.0];
        yield 'empty' => ['', null, false, null, null];
    }

    #[DataProvider('statuses')]
    public function testAStatusOfScormIsRead(string $status, ?string $score, bool $completed, ?bool $success, ?float $scaled): void
    {
        $outcome = ScormStatus::outcome($status, $score);

        self::assertSame($completed, $outcome->completed);
        self::assertSame($success, $outcome->success);
        self::assertSame($scaled, $outcome->score?->scaled);
    }

    public function testAWrongStatusOrScoreIsRefused(): void
    {
        foreach ([['done', null, '"done" is not a status SCORM writes.'], ['passed', 'high', 'A score is a number out of a hundred.'], ['passed', '101', 'A score out of a hundred is between 0 and 100.']] as [$status, $score, $message]) {
            try {
                ScormStatus::outcome($status, $score);
                self::fail('The status is accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame($message, $invalidCourse->getMessage());
            }
        }
    }

    public function testTheResultsOfAnotherPlatformAreImported(): void
    {
        $store = new Store();
        $course = $store->importer->import(Packages::zip(Packages::scorm12Files()))->course;

        $result = $store->results->import($course, [
            2 => ['learner' => 'ada', 'item' => 'intro', 'status' => 'completed', 'score' => '', 'date' => '2025-06-01T09:30:00-04:00'],
            3 => ['learner' => 'ada', 'item' => 'saws', 'status' => 'passed', 'score' => '90', 'date' => '2025-06-02'],
            4 => ['learner' => 'bob', 'item' => 'outro', 'status' => 'incomplete'],
            5 => ['learner' => 'cy', 'item' => 'missing', 'status' => 'passed'],
            6 => ['learner' => 'cy@example.org', 'item' => 'intro', 'status' => 'passed'],
            7 => ['learner' => 'cy', 'item' => 'intro', 'status' => 'passed', 'date' => 'yesterday'],
            8 => ['learner' => 'cy', 'item' => 'intro', 'status' => 'passed', 'date' => '2025-02-30'],
            9 => ['learner' => 'dee', 'item' => 'intro', 'status' => 'completed', 'date' => '2025-06-03 10:00:00'],
            10 => ['learner' => 'eve', 'item' => 'intro', 'status' => 'completed', 'date' => '2025-06-03T10:00:00.250+02:00'],
            11 => ['learner' => 'fay', 'item' => 'intro', 'status' => 'completed', 'date' => '2025-06-03T10:00:00'],
        ]);

        self::assertSame(6, $result['imported']);
        self::assertSame([5, 6, 7, 8], array_keys($result['refused']));
        self::assertSame('No activity of the course comes from the item "missing".', $result['refused'][5]);
        self::assertStringStartsWith('A learner identifier', $result['refused'][6]);
        self::assertSame('"yesterday" is not a moment in ISO 8601.', $result['refused'][7]);
        self::assertSame('"2025-02-30" is not a moment in ISO 8601.', $result['refused'][8]);

        $ada = $store->enrollments->of($course, LearnerId::of('ada'));
        self::assertNotNull($ada);
        $progresses = $store->enrollments->progresses($ada);
        $activities = [];

        foreach ($store->courses->outline($course)->activities() as $activity) {
            $activities[(string) $activity->source] = $activity->id->value;
        }

        self::assertSame('2025-06-01T13:30:00+00:00', $progresses[$activities['intro']]->satisfiedAt?->format(\DATE_ATOM));
        self::assertTrue($progresses[$activities['saws']]->success);
        self::assertSame(0.9, $progresses[$activities['saws']]->score?->scaled);
        self::assertSame('2025-06-02T00:00:00+00:00', $progresses[$activities['saws']]->updatedAt->format(\DATE_ATOM));
        $bob = $store->enrollments->of($course, LearnerId::of('bob'));
        self::assertNotNull($bob);
        self::assertSame([], $store->enrollments->progresses($bob));
        self::assertSame('2025-06-03T10:00:00+00:00', array_first($store->enrollments->progresses($store->enrollments->of($course, LearnerId::of('dee')) ?? $ada))?->updatedAt->format(\DATE_ATOM));
        self::assertSame('2025-06-03T08:00:00+00:00', array_first($store->enrollments->progresses($store->enrollments->of($course, LearnerId::of('eve')) ?? $ada))?->updatedAt->format(\DATE_ATOM));
        self::assertSame('2025-06-03T10:00:00+00:00', array_first($store->enrollments->progresses($store->enrollments->of($course, LearnerId::of('fay')) ?? $ada))?->updatedAt->format(\DATE_ATOM));
    }

    public function testAResultWithoutADateIsOfNow(): void
    {
        $store = new Store();
        $course = $store->importer->import(Packages::zip(Packages::scorm12Files()))->course;

        self::assertSame(1, $store->results->import($course, [['learner' => 'ada', 'item' => 'intro', 'status' => 'completed']])['imported']);
        $ada = $store->enrollments->of($course, LearnerId::of('ada'));
        self::assertNotNull($ada);
        self::assertSame('2026-11-13T10:00:00+00:00', array_first($store->enrollments->progresses($ada))?->updatedAt->format(\DATE_ATOM));
    }

    public function testTheRowsAreBounded(): void
    {
        $store = new Store();
        $course = $store->importer->import(Packages::zip(Packages::scorm12Files()))->course;
        $rows = (static function (): iterable {
            for ($index = 1; $index <= ResultImporter::MAX_ROWS + 5; ++$index) {
                yield $index => ['learner' => 'ada', 'item' => 'missing'];
            }
        })();

        $result = $store->results->import($course, $rows);

        self::assertSame(0, $result['imported']);
        self::assertCount(ResultImporter::MAX_ROWS + 1, $result['refused']);
        self::assertSame('Past 100000 rows, the rest is left out.', $result['refused'][ResultImporter::MAX_ROWS + 1]);
    }
}
