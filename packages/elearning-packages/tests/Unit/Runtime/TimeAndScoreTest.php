<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Runtime\Access;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\Score;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Duration::class)]
#[CoversClass(Score::class)]
#[CoversClass(RuntimeStatus::class)]
#[CoversClass(ScormVersion::class)]
#[CoversClass(Access::class)]
#[CoversClass(RuntimeRefused::class)]
final class TimeAndScoreTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function timespans(): iterable
    {
        yield 'hours, minutes, seconds' => ['0001:02:03', 372_300];
        yield 'two digits of hours' => ['01:00:00', 360_000];
        yield 'hundredths' => ['00:00:01.5', 150];
        yield 'two hundredths' => ['00:00:01.05', 105];
        yield 'the most' => ['9999:59:59.99', Duration::MAX_CENTISECONDS];
        yield 'one digit of hours' => ['1:00:00', null];
        yield 'five digits of hours' => ['10000:00:00', null];
        yield 'sixty minutes' => ['00:60:00', null];
        yield 'three hundredths' => ['00:00:01.005', null];
        yield 'ISO 8601' => ['PT1H', null];
    }

    #[DataProvider('timespans')]
    public function testATimespanOfScorm12IsRead(string $value, ?int $centiseconds): void
    {
        self::assertSame($centiseconds, Duration::parseTimespan($value)?->centiseconds);
        self::assertSame($centiseconds, Duration::parse(ScormVersion::Scorm12, $value)?->centiseconds);
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function intervals(): iterable
    {
        yield 'hours, minutes, seconds' => ['PT1H2M3S', 372_300];
        yield 'hundredths' => ['PT0.25S', 25];
        yield 'one decimal' => ['PT1.5S', 150];
        yield 'days' => ['P1D', 8_640_000];
        yield 'months of thirty days' => ['P1M', 259_200_000];
        yield 'years of 365 days' => ['P1Y', 3_153_600_000];
        yield 'minutes alone' => ['PT90M', 540_000];
        yield 'everything' => ['P1DT1H1M1.01S', 8_640_000 + 366_101];
        yield 'nothing' => ['P', null];
        yield 'a time of nothing' => ['PT', null];
        yield 'three decimals' => ['PT1.005S', null];
        yield 'a week' => ['P1W', null];
        yield 'a timespan' => ['01:00:00', null];
        yield 'too long' => ['PT'.str_repeat('1', 62).'S', null];
        yield 'huge' => ['PT'.str_repeat('9', 30).'S', Duration::MAX_CENTISECONDS];
    }

    #[DataProvider('intervals')]
    public function testADurationOfScorm2004IsRead(string $value, ?int $centiseconds): void
    {
        self::assertSame($centiseconds, Duration::parseInterval($value)?->centiseconds);
        self::assertSame($centiseconds, Duration::parse(ScormVersion::Scorm2004, $value)?->centiseconds);
    }

    public function testADurationIsAddedAndWrittenInEachFormat(): void
    {
        $total = Duration::ofCentiseconds(372_350)->plus(Duration::ofCentiseconds(5));

        self::assertSame('0001:02:03.55', $total->format(ScormVersion::Scorm12));
        self::assertSame('PT1H2M3.55S', $total->format(ScormVersion::Scorm2004));
        self::assertSame('PT0H0M0S', Duration::zero()->interval());
        self::assertSame('0000:00:00.00', Duration::zero()->timespan());
        self::assertSame('PT0H1M0.05S', Duration::ofCentiseconds(6005)->interval());
        self::assertSame(0, Duration::ofCentiseconds(-5)->centiseconds);
        self::assertSame(Duration::MAX_CENTISECONDS, Duration::ofCentiseconds(Duration::MAX_CENTISECONDS)->plus(Duration::ofCentiseconds(1))->centiseconds);
        self::assertSame('9999:59:59.99', Duration::ofCentiseconds(\PHP_INT_MAX)->timespan());
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string, ?string, bool, ?float}>
     */
    public static function scores(): iterable
    {
        yield 'a scaled score' => ['0.5', '10', '0', '100', false, 0.5];
        yield 'a scaled score out of bounds' => ['3', null, null, null, false, 1.0];
        yield 'a blank scaled score' => ['', '50', '0', '100', false, 0.5];
        yield 'a raw score in its range' => [null, '30', '20', '40', false, 0.5];
        yield 'a raw score below its range' => [null, '-10', '0', '10', false, -1.0];
        yield 'a raw score without a range' => [null, '30', null, null, false, null];
        yield 'a raw score out of a hundred' => [null, '30', null, null, true, 0.3];
        yield 'a raw score of 1.2 with a maximum' => [null, '15', null, '20', true, 0.75];
        yield 'a raw score of 1.2 with a minimum' => [null, '60', '20', null, true, 0.5];
        yield 'an empty range' => [null, '30', '40', '40', false, null];
        yield 'a range upside down' => [null, '30', '40', '20', false, null];
        yield 'a raw score that is not a number' => [null, 'x', '0', '100', false, null];
        yield 'nothing' => [null, null, null, null, true, null];
    }

    #[DataProvider('scores')]
    public function testAScoreIsScaledFromWhatTheContentSets(?string $scaled, ?string $raw, ?string $min, ?string $max, bool $outOfHundred, ?float $expected): void
    {
        self::assertSame($expected, Score::scaled($scaled, $raw, $min, $max, $outOfHundred));
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?bool}>
     */
    public static function lessonStatuses(): iterable
    {
        yield 'passed' => ['passed', 'completed', 'passed', 'passed', true];
        yield 'failed' => ['failed', 'completed', 'failed', 'failed', false];
        yield 'completed' => ['completed', 'completed', 'unknown', 'completed', null];
        yield 'incomplete' => ['incomplete', 'incomplete', 'unknown', 'incomplete', null];
        yield 'browsed' => ['browsed', 'incomplete', 'unknown', 'incomplete', null];
        yield 'not attempted' => ['not attempted', 'not attempted', 'unknown', 'not attempted', null];
    }

    #[DataProvider('lessonStatuses')]
    public function testALessonStatusReadsAsAdlMapsIt(string $lessonStatus, string $completion, string $success, string $back, ?bool $passed): void
    {
        $status = RuntimeStatus::fromLessonStatus($lessonStatus, 0.5);

        self::assertSame([$completion, $success, 0.5], [$status->completion, $status->success, $status->score]);
        self::assertSame($back, $status->lessonStatus());
        self::assertSame($passed, $status->success());
        self::assertSame('completed' === $completion, $status->isCompleted());
    }

    public function testAStatusOf2004ReadsAsALessonStatus(): void
    {
        self::assertSame('not attempted', new RuntimeStatus()->lessonStatus());
        self::assertSame('not attempted', new RuntimeStatus('unknown', 'unknown')->lessonStatus());
        self::assertSame('passed', new RuntimeStatus('incomplete', 'passed')->lessonStatus());
        self::assertNull(new RuntimeStatus()->score);
    }

    public function testEachVersionNamesItsApiAndElements(): void
    {
        self::assertSame(ScormVersion::Scorm12, ScormVersion::of(PackageFormat::Scorm12));
        self::assertSame(ScormVersion::Scorm2004, ScormVersion::of(PackageFormat::Scorm2004));
        self::assertSame(['API', 'API_1484_11'], [ScormVersion::Scorm12->apiName(), ScormVersion::Scorm2004->apiName()]);
        self::assertSame(['3.4', '1.0'], [ScormVersion::Scorm12->dataModelVersion(), ScormVersion::Scorm2004->dataModelVersion()]);
        self::assertSame(['cmi.core.lesson_status', 'cmi.completion_status'], [ScormVersion::Scorm12->statusElement(), ScormVersion::Scorm2004->statusElement()]);
        self::assertSame(['cmi.core.exit', 'cmi.exit'], [ScormVersion::Scorm12->core('exit'), ScormVersion::Scorm2004->core('exit')]);
        self::assertSame([true, false, true], [Access::ReadOnly->readable(), Access::WriteOnly->readable(), Access::ReadWrite->readable()]);
        self::assertSame([false, true, true], [Access::ReadOnly->writable(), Access::WriteOnly->writable(), Access::ReadWrite->writable()]);

        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIs('A cmi5 unit talks xAPI, not the SCORM run-time API.');

        ScormVersion::of(PackageFormat::Cmi5);
    }
}
