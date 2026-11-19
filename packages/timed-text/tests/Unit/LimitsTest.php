<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Exception\InvalidLimits;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Inspection;
use Phil\TimedText\Internal\Input;
use Phil\TimedText\Internal\IssueLog;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limits::class)]
#[CoversClass(Input::class)]
#[CoversClass(IssueLog::class)]
#[CoversClass(Issue::class)]
#[CoversClass(Inspection::class)]
#[CoversClass(InvalidTimedText::class)]
#[CoversClass(LimitExceeded::class)]
final class LimitsTest extends TestCase
{
    public function testItHasDefaultsForADayOfTranscript(): void
    {
        $limits = new Limits();

        self::assertSame([16_777_216, 100_000, 65_536, 1_000], [$limits->maxBytes, $limits->maxCues, $limits->maxTextBytes, $limits->maxIssues]);
        self::assertSame([100_000, 1_000, 1_000], [$limits->maxNotes, $limits->maxRegions, $limits->maxStyles]);
        self::assertSame([1, 1, 1, 1], [new Limits(1, 1, 1, 1, 1, 1, 1)->maxIssues, new Limits(maxNotes: 1)->maxNotes, new Limits(maxRegions: 1)->maxRegions, new Limits(maxStyles: 1)->maxStyles]);
    }

    /**
     * @return iterable<string, list<int>>
     */
    public static function belowOne(): iterable
    {
        yield 'bytes' => [0, 1, 1, 1, 1, 1, 1];
        yield 'cues' => [1, 0, 1, 1, 1, 1, 1];
        yield 'text' => [1, 1, 0, 1, 1, 1, 1];
        yield 'issues' => [1, 1, 1, 0, 1, 1, 1];
        yield 'notes' => [1, 1, 1, 1, 0, 1, 1];
        yield 'regions' => [1, 1, 1, 1, 1, 0, 1];
        yield 'styles' => [1, 1, 1, 1, 1, 1, 0];
    }

    #[DataProvider('belowOne')]
    public function testEveryLimitIsAtLeastOne(int $bytes, int $cues, int $text, int $issues, int $notes, int $regions, int $styles): void
    {
        $this->expectException(InvalidLimits::class);
        $this->expectExceptionMessageIsOrContains('Every limit is at least 1.');

        new Limits($bytes, $cues, $text, $issues, $notes, $regions, $styles);
    }

    public function testTheInputIsCheckedAndCleaned(): void
    {
        $limits = new Limits(maxBytes: 8, maxTextBytes: 3);

        self::assertSame('abc', Input::text("\u{FEFF}abc", $limits));
        self::assertSame('x?y', Input::text("x\xFFy", $limits));
        self::assertSame('éa', Input::text('éa', $limits));
        self::assertSame(['a', 'b', 'c', 'd', ''], Input::lines("a\r\nb\rc\nd\n", new Limits()));
        self::assertSame([''], Input::lines('', $limits));
        self::assertSame('abc', Input::checkText('abc', 1, $limits));
        self::assertSame(8, \strlen(Input::text('12345678', $limits)));

        try {
            Input::checkText('abcd', 7, $limits);
            self::fail('A text longer than the limit was read.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('Line 7: a text is longer than 3 bytes.', $limitExceeded->getMessage());
        }

        try {
            Input::checkText('abcd', null, $limits);
            self::fail('A text longer than the limit was read.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('A text is longer than 3 bytes.', $limitExceeded->getMessage());
        }

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document is 9 bytes; the limit is 8.');

        Input::text('123456789', $limits);
    }

    public function testAStrictLogThrowsAtTheFirstProblem(): void
    {
        try {
            new IssueLog(true, new Limits())->report(4, 'Wrong.');
            self::fail('A strict log listed a problem.');
        } catch (InvalidTimedText $invalidTimedText) {
            self::assertSame('Line 4: Wrong.', $invalidTimedText->getMessage());
            self::assertSame(4, $invalidTimedText->documentLine);
        }

        $withoutLine = InvalidTimedText::at(null, 'No line.');
        self::assertSame('No line.', $withoutLine->getMessage());
        self::assertNull($withoutLine->documentLine);
    }

    public function testALenientLogListsUpToTheLimit(): void
    {
        $log = new IssueLog(false, new Limits(maxIssues: 2));
        $log->report(1, 'One.');
        $log->report(null, 'Two.');

        self::assertSame(['Line 1: One.', 'Two.'], array_map(static fn (Issue $issue): string => $issue->toString(), $log->issues()));
        self::assertSame([1, 'One.'], [$log->issues()[0]->line, $log->issues()[0]->message]);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document holds more than 2 problems.');

        $log->report(3, 'Three.');
    }

    public function testAnInspectionSaysWhetherTheDocumentWasValid(): void
    {
        $track = new Track();

        self::assertTrue(new Inspection($track)->isValid());
        self::assertFalse(new Inspection($track, [new Issue(1, 'x')])->isValid());
        self::assertSame($track, new Inspection($track)->track);
    }
}
