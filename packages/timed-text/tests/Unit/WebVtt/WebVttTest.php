<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit\WebVtt;

use Phil\TimedText\Cue;
use Phil\TimedText\CueSettings;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Internal\WebVttParser;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;
use Phil\TimedText\Note;
use Phil\TimedText\Region;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\Track;
use Phil\TimedText\WebVtt\WebVttReader;
use Phil\TimedText\WebVtt\WebVttWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebVttReader::class)]
#[CoversClass(WebVttWriter::class)]
#[CoversClass(WebVttParser::class)]
final class WebVttTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/full.vtt';

    public function testItReadsEveryPartOfAFile(): void
    {
        $track = new WebVttReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertSame('Episode 12 - captions', $track->header);
        self::assertCount(1, $track->regions);
        self::assertSame('speakers', $track->regions[0]->id);
        self::assertSame(['width' => '40%', 'lines' => '3', 'regionanchor' => '0%,100%', 'viewportanchor' => '10%,90%', 'scroll' => 'up'], $track->regions[0]->settings);
        self::assertSame(["::cue(.loud) {\n  font-weight: bold;\n}"], $track->styles);
        self::assertSame(['Recorded live; the timings were checked by hand.', 'Second half.'], array_map(static fn (Note $note): string => $note->text, $track->notes));
        self::assertSame([1_000, 3_723_004], [$track->notes[0]->at, $track->notes[1]->at]);
        self::assertCount(3, $track->cues);

        [$intro, $second, $last] = $track->cues;
        self::assertSame(['intro', 1_000, 4_500, '<v Alice>Welcome to the show &amp; thanks for listening.'], [$intro->id, $intro->start, $intro->end, $intro->text]);
        self::assertSame(['region' => 'speakers', 'align' => 'start'], $intro->settings->values);
        self::assertSame("<v Bob>Glad to be here.\n<c.loud>Really</c> <i>glad</i>.", $second->text);
        self::assertSame(['line' => '85%', 'position' => '50%,center', 'size' => '80%'], $second->settings->values);
        self::assertNull($second->id);
        self::assertSame([3_723_004, 3_725_000, 'Alice'], [$last->start, $last->end, $last->speaker()]);
    }

    public function testItWritesBackWhatItRead(): void
    {
        $text = (string) file_get_contents(self::FIXTURE);
        $written = new WebVttWriter()->write(new WebVttReader()->read($text));
        $expected = str_replace(
            ["id:speakers width:40% lines:3\nregionanchor:0%,100% viewportanchor:10%,90% scroll:up", "NOTE\nSecond half."],
            ["id:speakers\nwidth:40%\nlines:3\nregionanchor:0%,100%\nviewportanchor:10%,90%\nscroll:up", 'NOTE Second half.'],
            $text,
        );

        self::assertSame($expected, $written);
        self::assertSame($written, new WebVttWriter()->write(new WebVttReader()->read($written)));
    }

    public function testItDropsTheStylesWhenAsked(): void
    {
        $text = (string) file_get_contents(self::FIXTURE);

        self::assertSame([], new WebVttReader(styles: StylePolicy::Drop)->read($text)->styles);
        self::assertStringNotContainsString('STYLE', new WebVttWriter(StylePolicy::Drop)->write(new WebVttReader()->read($text)));
    }

    public function testItReadsTheSmallestFiles(): void
    {
        self::assertSame('', new WebVttReader()->read('WEBVTT')->header);
        self::assertTrue(new WebVttReader()->read("WEBVTT\n")->isEmpty());
        self::assertSame('x', new WebVttReader()->read("\u{FEFF}WEBVTT\tx  ")->header);
        self::assertSame("WEBVTT\n", new WebVttWriter()->write(new Track()));
    }

    public function testItReadsEveryLineEnding(): void
    {
        $track = new WebVttReader()->read("WEBVTT\r\n\r\n00:01.000 --> 00:02.000\r\nA\rB\n\n00:03.000-->00:04.000\nC");

        self::assertSame(["A\nB", 'C'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
        self::assertSame([1_000, 3_000], [$track->cues[0]->start, $track->cues[1]->start]);
    }

    public function testItSkipsTheMetadataUnderTheFirstLine(): void
    {
        $track = new WebVttReader()->read("WEBVTT\nKind: captions\nLanguage: en\n\n00:01.000 --> 00:02.000\nA");

        self::assertSame(['A'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testACueRightUnderTheHeaderIsRead(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n00:01.000 --> 00:02.000\nA");

        self::assertSame(['A'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testACueWithoutTextIsRead(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\n00:01.000 --> 00:02.000\n\n00:03.000 --> 00:04.000");

        self::assertSame(['', ''], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
        self::assertSame("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n\n00:00:03.000 --> 00:00:04.000\n", new WebVttWriter()->write($track));
    }

    public function testALineWithAnArrowStartsTheNextCue(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\n00:01.000 --> 00:02.000\nA\n00:03.000 --> 00:04.000\nB");

        self::assertSame(['A', 'B'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testNotesAtTheEndAreNotAnchored(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\nNOTE\n\n00:01.000 --> 00:02.000\nA\n\nNOTE closing\nwords");

        self::assertSame(['', "closing\nwords"], array_map(static fn (Note $note): string => $note->text, $track->notes));
        self::assertSame([1_000, null], [$track->notes[0]->at, $track->notes[1]->at]);
        self::assertSame("WEBVTT\n\nNOTE\n\n00:00:01.000 --> 00:00:02.000\nA\n\nNOTE closing\nwords\n", new WebVttWriter()->write($track));
    }

    public function testTheWriterPlacesNotesByTheirAnchor(): void
    {
        $track = new Track(
            [new Cue(1_000, 2_000, 'A'), new Cue(3_000, 4_000, 'B')],
            notes: [new Note('second', 2_500), new Note('end'), new Note('first', 0), new Note('also first', 1_000)],
        );

        self::assertSame(
            "WEBVTT\n\nNOTE first\n\nNOTE also first\n\n00:00:01.000 --> 00:00:02.000\nA\n\nNOTE second\n\n00:00:03.000 --> 00:00:04.000\nB\n\nNOTE end\n",
            new WebVttWriter()->write($track),
        );
    }

    public function testTheWriterWritesIdentifiersAndSettings(): void
    {
        $track = new Track([new Cue(0, 1, 'x', 'one', new CueSettings(['vertical' => 'rl', 'line' => '0']))], [new Region('r', ['lines' => '2'])], header: 'h');

        self::assertSame("WEBVTT h\n\nREGION\nid:r\nlines:2\n\none\n00:00:00.000 --> 00:00:00.001 vertical:rl line:0\nx\n", new WebVttWriter()->write($track));
    }

    public function testItRefusesAFileThatDoesNotOpenWithWebVtt(): void
    {
        foreach (['', 'WEBVTTX', 'webvtt', ' WEBVTT', "\n\nWEBVTT"] as $text) {
            try {
                new WebVttReader()->inspect($text);
                self::fail('Read as WebVTT: '.json_encode($text));
            } catch (InvalidTimedText $exception) {
                self::assertSame('Line 1: A WebVTT file opens with "WEBVTT".', $exception->getMessage());
                self::assertSame(1, $exception->documentLine);
            }
        }
    }

    public function testAnInspectionListsTheProblemsAndKeepsTheRest(): void
    {
        $text = implode("\n", [
            'WEBVTT bad --> header',             // 1
            '',                                   // 2
            'REGION',                             // 3
            'width:40% colour:red',               // 4
            '',                                   // 5
            'REGION',                             // 6
            'id:a',                               // 7
            '',                                   // 8
            'REGION',                             // 9
            'id:a lines:2',                       // 10
            '',                                   // 11
            'STYLE',                              // 12
            '00:00.000 --> 00:01.000',            // 13
            'kept',                               // 14
            '',                                   // 15
            'stray words',                        // 16
            'more words',                         // 17
            '',                                   // 18
            'id',                                 // 19
            '00:05.000 -> 00:06.000',             // 20
            '',                                   // 21
            '00:07.000 --> 00:06.000',            // 22
            'reversed',                           // 23
            '',                                   // 24
            '00:08.000 --> 00:09.00',             // 25
            '',                                   // 26
            '00:00:04.000 --> 00:00:05.000 align:middle line:5%', // 27
            'early',                              // 28
            '',                                   // 29
            'STYLE',                              // 30
            'p {}',                               // 31
            '',                                   // 32
            'REGION',                             // 33
            'id:late',                            // 34
            '',                                   // 35
            'NOTE broken',                        // 36
            '00:10.000 --> 00:11.000',            // 37
            'after the note',                     // 38
            '',                                   // 39
            'id only',                            // 40
        ]);

        $inspection = new WebVttReader()->inspect($text);

        self::assertSame([
            'Line 1: The header holds "-->"; it is dropped.',
            'Line 3: "colour:red" is not a region setting; it is ignored.',
            'Line 3: A region without an identifier is ignored.',
            'Line 9: The region "a" is already defined; this one is ignored.',
            'Line 12: A STYLE block holds "-->"; it is ignored.',
            'Line 16: A block is neither a cue, a note, a style nor a region; it is ignored.',
            'Line 19: A block is neither a cue, a note, a style nor a region; it is ignored.',
            'Line 22: A cue ends before it starts.',
            'Line 25: "00:09.00" is not a WebVTT timestamp.',
            'Line 27: "align:middle" is not a cue setting; it is ignored.',
            'Line 30: A STYLE block after the first cue is ignored.',
            'Line 33: A REGION block after the first cue is ignored.',
            'Line 37: A note ends at a line with "-->", which starts the next block.',
            'Line 40: A block is neither a cue, a note, a style nor a region; it is ignored.',
        ], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));

        $track = $inspection->track;
        self::assertSame('', $track->header);
        self::assertSame(['a'], array_map(static fn (Region $region): string => $region->id, $track->regions));
        self::assertSame([], $track->styles);
        self::assertSame(['kept', 'early', 'after the note'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
        self::assertSame(['line' => '5%'], $track->cues[1]->settings->values);
        self::assertSame([['broken', 10_000]], array_map(static fn (Note $note): array => [$note->text, $note->at], $track->notes));
        self::assertFalse($inspection->isValid());
    }

    public function testAnInspectionOfAValidFileFindsNothing(): void
    {
        self::assertTrue(new WebVttReader()->inspect((string) file_get_contents(self::FIXTURE))->isValid());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function strictProblems(): iterable
    {
        yield 'timestamp' => ["WEBVTT\n\n00:01 --> 00:02.000\nA", 'Line 3: "00:01" is not a WebVTT timestamp.'];
        yield 'end timestamp' => ["WEBVTT\n\n00:01.000 --> 2\nA", 'Line 3: "2" is not a WebVTT timestamp.'];
        yield 'order' => ["WEBVTT\n\n00:05.000 --> 00:06.000\n\n00:01.000 --> 00:02.000", 'Line 5: A cue starts before the cue above it.'];
        yield 'setting' => ["WEBVTT\n\n00:01.000 --> 00:02.000 size:2\nA", 'Line 3: "size:2" is not a cue setting; it is ignored.'];
        yield 'timings' => ["WEBVTT\n\n00:01.000 --> next\nA", 'Line 3: The timings of a cue are "start --> end", then its settings.'];
        yield 'identifier without timings' => ["WEBVTT\n\nalone", 'Line 3: A block is neither a cue'];
    }

    #[DataProvider('strictProblems')]
    public function testReadingRefusesAtTheFirstProblem(string $text, string $message): void
    {
        $this->expectException(InvalidTimedText::class);
        $this->expectExceptionMessageIsOrContains($message);

        new WebVttReader()->read($text);
    }

    public function testTheLimitsBoundTheWork(): void
    {
        $cues = "WEBVTT\n\n00:01.000 --> 00:02.000\nA\n\n00:03.000 --> 00:04.000\nB";

        self::assertCount(2, new WebVttReader(new Limits(maxCues: 2))->read($cues)->cues);

        foreach ([
            [new Limits(maxCues: 1), $cues, 'The document holds more than 1 cues.'],
            [new Limits(maxBytes: 10), $cues, 'The document is 60 bytes; the limit is 10.'],
            [new Limits(maxTextBytes: 3), "WEBVTT\n\n00:01.000 --> 00:02.000\nABCD", 'Line 4: a text is longer than 3 bytes.'],
            [new Limits(maxTextBytes: 3), "WEBVTT\n\nNOTE ABCD", 'Line 3: a text is longer than 3 bytes.'],
            [new Limits(maxTextBytes: 3), "WEBVTT\n\nSTYLE\nABCD", 'Line 3: a text is longer than 3 bytes.'],
            [new Limits(maxIssues: 1), "WEBVTT\n\nx\n\ny", 'The document holds more than 1 problems.'],
        ] as [$limits, $text, $message]) {
            try {
                new WebVttReader($limits)->inspect($text);
                self::fail('Read past the limits: '.$message);
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheNotesRegionsAndStylesOfAFileAreCountedBeforeTheyAreRead(): void
    {
        $notes = "WEBVTT\n\nNOTE one\n\n00:01.000 --> 00:02.000\nA\n\nNOTE two";
        $regions = "WEBVTT\n\nREGION\nid:a\n\nREGION\nid:b\n\n00:01.000 --> 00:02.000\nA";
        $styles = "WEBVTT\n\nSTYLE\n::cue {}\n\nSTYLE\n::cue(b) {}";

        self::assertCount(2, new WebVttReader(new Limits(maxNotes: 2))->read($notes)->notes);
        self::assertCount(2, new WebVttReader(new Limits(maxRegions: 2))->read($regions)->regions);
        self::assertCount(2, new WebVttReader(new Limits(maxStyles: 2))->read($styles)->styles);
        self::assertSame([], new WebVttReader(new Limits(maxStyles: 2), StylePolicy::Drop)->read($styles)->styles);

        foreach ([
            [new Limits(maxNotes: 1), $notes, 'The document holds more than 1 notes.'],
            [new Limits(maxNotes: 1), "WEBVTT\n\nNOTE one\n\nNOTE two", 'The document holds more than 1 notes.'],
            [new Limits(maxRegions: 1), $regions, 'The document holds more than 1 regions.'],
            [new Limits(maxStyles: 1), $styles, 'The document holds more than 1 style blocks.'],
            [new Limits(maxStyles: 1), $styles, 'The document holds more than 1 style blocks.', StylePolicy::Drop],
        ] as $case) {
            [$limits, $text, $message] = $case;

            try {
                new WebVttReader($limits, $case[3] ?? StylePolicy::Keep)->inspect($text);
                self::fail('Read past the limits: '.$message);
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testAFileOfNotesUnderTheByteLimitIsRefusedAtItsBudget(): void
    {
        $limits = new Limits(maxBytes: 65_536, maxNotes: 1_000);
        $hostile = 'WEBVTT'.str_repeat("\n\nNOTE", 5_000);

        self::assertLessThan($limits->maxBytes, \strlen($hostile));
        self::assertCount(1_000, new WebVttReader($limits)->read('WEBVTT'.str_repeat("\n\nNOTE", 1_000))->notes);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document holds more than 1000 notes.');

        new WebVttReader($limits)->read($hostile);
    }

    public function testRegionsAreComparedByIdentifierWithoutReadingThemAll(): void
    {
        $hostile = 'WEBVTT';

        for ($i = 0; $i < 999; ++$i) {
            $hostile .= "\n\nREGION\nid:r".$i;
        }

        $inspection = new WebVttReader(new Limits(maxBytes: 65_536))->inspect($hostile."\n\nREGION\nid:r5");

        self::assertCount(999, $inspection->track->regions);
        self::assertSame(['Line 3000: The region "r5" is already defined; this one is ignored.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
        self::assertCount(1_000, new WebVttReader()->read($hostile."\n\nREGION\nid:last")->regions);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document holds more than 1000 regions.');

        new WebVttReader()->read($hostile."\n\nREGION\nid:last\n\nREGION\nid:extra");
    }
}
