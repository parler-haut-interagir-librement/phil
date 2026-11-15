<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit\Srt;

use Phil\TimedText\Cue;
use Phil\TimedText\CueSettings;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Internal\SrtParser;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;
use Phil\TimedText\Note;
use Phil\TimedText\Srt\SrtReader;
use Phil\TimedText\Srt\SrtWriter;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrtReader::class)]
#[CoversClass(SrtWriter::class)]
#[CoversClass(SrtParser::class)]
final class SrtTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/sample.srt';

    public function testItReadsAFile(): void
    {
        $track = new SrtReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertSame([[1_000, 4_500], [4_500, 8_250]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $track->cues));
        self::assertSame(["Welcome to the show &amp; thanks\nfor <i>listening</i>.", 'Glad to be <b>here</b>.'], $this->texts($track));
        self::assertNull($track->cues[0]->id);
    }

    public function testItWritesBackWhatItRead(): void
    {
        $expected = str_replace(['<font color="#ffff00">Glad</font>', '<B>here</B>'], ['Glad', '<b>here</b>'], (string) file_get_contents(self::FIXTURE));

        self::assertSame($expected, new SrtWriter()->write(new SrtReader()->read((string) file_get_contents(self::FIXTURE))));
    }

    public function testItWritesOnlyWhatSrtCarries(): void
    {
        $track = new Track(
            [new Cue(0, 1_500, "<v Alice>Hi &amp; <c.x>bye</c>\n<b>x</b>", 'id', new CueSettings(['align' => 'start'])), new Cue(3_600_000, 3_600_001, ''), new Cue(5_000_000, 5_000_001, '<c></c>')],
            notes: [new Note('note')],
            header: 'header',
        );

        self::assertSame("1\n00:00:00,000 --> 00:00:01,500\nHi & bye\n<b>x</b>\n\n2\n01:00:00,000 --> 01:00:00,001\n\n3\n01:23:20,000 --> 01:23:20,001\n", new SrtWriter()->write($track));
        self::assertSame('', new SrtWriter()->write(new Track()));
    }

    public function testItReadsWhatEditorsWrite(): void
    {
        $track = new SrtReader()->inspect("\u{FEFF}1\r\n0:00:01.5 --> 0:00:02,000 X1:10 X2:20\r\n  \r\n2\n00:00:03,000-->00:00:04,000\nA\n3\n00:00:05,000 --> 00:00:06,000\nB\n00:00:07,000 --> 00:00:08,000\nC\n42\nnot a number line")->track;

        self::assertSame([[1_500, 2_000], [3_000, 4_000], [5_000, 6_000], [7_000, 8_000]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $track->cues));
        self::assertSame(['', 'A', 'B', "C\n42\nnot a number line"], $this->texts($track));
    }

    public function testAnInspectionListsTheProblemsAndKeepsTheRest(): void
    {
        $text = implode("\n", [
            'stray words',                       // 1
            '',                                  // 2
            '1',                                 // 3
            'no timings',                        // 4
            '',                                  // 5
            '2',                                 // 6
            '00:00:05,000 --> 00:00:04,000',     // 7
            '',                                  // 8
            '3',                                 // 9
            '00:00:05,000 --> 00:00:61,000',     // 10
            '',                                  // 11
            '4',                                 // 12
            '00:00:x --> 00:00:06,000',          // 13
            '',                                  // 14
            '00:00:08,000 --> 00:00:09,000',     // 15
            'no number',                         // 16
            '',                                  // 17
            '5',                                 // 18
            '00:00:01,000 --> 00:00:02,000',     // 19
            'early',                             // 20
            '',                                  // 21
            '6',                                 // 22
        ]);

        $inspection = new SrtReader()->inspect($text);

        self::assertSame([
            'Line 1: A block without timings is ignored.',
            'Line 4: The timings of a cue are "start --> end".',
            'Line 7: A cue ends before it starts.',
            'Line 10: "00:00:61,000" is not an SRT timestamp.',
            'Line 13: The timings of a cue are "start --> end".',
            'Line 15: A cue opens with its number.',
            'Line 19: A cue starts before the cue above it.',
            'Line 23: The timings of a cue are "start --> end".',
        ], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
        self::assertSame(['no number', 'early'], $this->texts($inspection->track));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function strictProblems(): iterable
    {
        yield 'start' => ["1\n00:00:01 --> 00:00:02,000\nA", 'Line 2: "00:00:01" is not an SRT timestamp.'];
        yield 'number' => ["00:00:01,000 --> 00:00:02,000\nA", 'Line 1: A cue opens with its number.'];
    }

    #[DataProvider('strictProblems')]
    public function testReadingRefusesAtTheFirstProblem(string $text, string $message): void
    {
        $this->expectException(InvalidTimedText::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SrtReader()->read($text);
    }

    public function testTheLimitsBoundTheWork(): void
    {
        $text = "1\n00:00:01,000 --> 00:00:02,000\nA\n\n2\n00:00:03,000 --> 00:00:04,000\nB";

        self::assertCount(2, new SrtReader(new Limits(maxCues: 2))->read($text)->cues);

        foreach ([
            [new Limits(maxCues: 1), $text, 'The document holds more than 1 cues.'],
            [new Limits(maxBytes: 10), $text, 'The document is 68 bytes; the limit is 10.'],
            [new Limits(maxTextBytes: 3), "1\n00:00:01,000 --> 00:00:02,000\nAB\nC", 'Line 3: a text is longer than 3 bytes.'],
        ] as [$limits, $input, $message]) {
            try {
                new SrtReader($limits)->read($input);
                self::fail('Read past the limits: '.$message);
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    /**
     * @return list<string>
     */
    private function texts(Track $track): array
    {
        return array_map(static fn (Cue $cue): string => $cue->text, $track->cues);
    }
}
