<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit\Html;

use Phil\TimedText\Cue;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Html\HtmlTranscriptReader;
use Phil\TimedText\Html\HtmlTranscriptWriter;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlTranscriptReader::class)]
#[CoversClass(HtmlTranscriptWriter::class)]
final class HtmlTranscriptTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/transcript.html';

    public function testItReadsATranscript(): void
    {
        $track = new HtmlTranscriptReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertSame([[500, 2_000], [2_000, 3_780_000], [2_000, 3_780_000], [3_780_000, 3_780_000]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $track->cues));
        self::assertSame(["<v Alice>Hello &amp; welcome\neveryone.", '<v Bob>Thanks.', '<v Bob>Really.', '<v Bob>Goodbye.'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testItWritesATranscriptEscapedByConstruction(): void
    {
        $track = new Track([
            new Cue(500, 2_000, "<v Alice &quot;A&quot;>Hello &amp; <b>welcome</b>\n&lt;script&gt;alert(1)&lt;/script&gt;"),
            new Cue(2_000, 3_000, '<v Alice &quot;A&quot;>Still me.'),
            new Cue(3_000, 4_000, 'Nobody named.'),
            new Cue(3_725_000, 3_726_000, '<v Bob>Late.'),
        ]);

        self::assertSame(
            "<cite>Alice &quot;A&quot;:</cite>\n<time datetime=\"PT0.5S\">0:00</time>\n<p>Hello &amp; welcome<br>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n"
            ."<time datetime=\"PT2S\">0:02</time>\n<p>Still me.</p>\n"
            ."<time datetime=\"PT3S\">0:03</time>\n<p>Nobody named.</p>\n"
            ."<cite>Bob:</cite>\n<time datetime=\"PT3725S\">1:02:05</time>\n<p>Late.</p>\n",
            new HtmlTranscriptWriter()->write($track),
        );
        self::assertSame('', new HtmlTranscriptWriter()->write(new Track()));
    }

    public function testItReadsBackWhatItWrote(): void
    {
        $track = new Track([new Cue(500, 2_000, "<v Alice>One &amp;\ntwo"), new Cue(2_000, 2_500, '<v Bob>Three'), new Cue(2_500, 2_500, '<v Bob>Four')]);
        $read = new HtmlTranscriptReader()->read(new HtmlTranscriptWriter()->write($track));

        self::assertEquals($track, $read);
    }

    public function testAnInspectionListsTheProblems(): void
    {
        $inspection = new HtmlTranscriptReader()->inspect('<time>0:05</time><p>a</p><time>soon</time><p>b</p><time datetime="PT1S">x</time><p>c</p><p>   </p><time datetime=" PT9S ">x</time><p>d</p>');

        self::assertSame(['Time 2: it is neither an ISO 8601 duration nor a clock.', 'Time 3: it comes before the time above it.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
        self::assertSame([[5_000, 9_000, 'a'], [5_000, 9_000, 'b'], [5_000, 9_000, 'c'], [9_000, 9_000, 'd']], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end, $cue->text], $inspection->track->cues));
    }

    public function testAParagraphBeforeAnyTimeStartsAtZero(): void
    {
        $track = new HtmlTranscriptReader()->read("<cite> Ann : </cite><p>  first\n  line <i>x</i> </p><cite></cite><p>\u{E000}second</p>");

        self::assertSame([[0, 0, '<v Ann>first line x'], [0, 0, 'second']], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end, $cue->text], $track->cues));
    }

    public function testReadingRefusesAtTheFirstProblem(): void
    {
        $this->expectException(InvalidTimedText::class);
        $this->expectExceptionMessageIsOrContains('Time 1: it is neither an ISO 8601 duration nor a clock.');

        new HtmlTranscriptReader()->read('<time>later</time><p>a</p>');
    }

    public function testTheLimitsBoundTheWork(): void
    {
        $html = '<p>ab</p><p>cd</p>';

        self::assertCount(2, new HtmlTranscriptReader(new Limits(maxCues: 2, maxTextBytes: 2))->read($html)->cues);

        foreach ([
            [new Limits(maxCues: 1), 'The document holds more than 1 paragraphs.'],
            [new Limits(maxBytes: 10), 'The document is 18 bytes; the limit is 10.'],
            [new Limits(maxTextBytes: 1), 'A text is longer than 1 bytes.'],
        ] as [$limits, $message]) {
            try {
                new HtmlTranscriptReader($limits)->read($html);
                self::fail('Read past the limits: '.$message);
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
