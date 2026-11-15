<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\CueSettings;
use Phil\TimedText\CueText;
use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Html\HtmlTranscriptReader;
use Phil\TimedText\Html\HtmlTranscriptWriter;
use Phil\TimedText\Internal\CueToken;
use Phil\TimedText\Internal\SrtParser;
use Phil\TimedText\Internal\WebVttParser;
use Phil\TimedText\Issue;
use Phil\TimedText\Note;
use Phil\TimedText\Podcast\JsonChapterReader;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\Region;
use Phil\TimedText\Srt\SrtReader;
use Phil\TimedText\Srt\SrtWriter;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;
use Phil\TimedText\WebVtt\WebVttReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of each limit, range, pattern and comparison of the package: the value on the line and the one beside it.
 */
#[CoversClass(Chapter::class)]
#[CoversClass(ChapterList::class)]
#[CoversClass(Cue::class)]
#[CoversClass(CueSettings::class)]
#[CoversClass(CueText::class)]
#[CoversClass(InvalidTimedText::class)]
#[CoversClass(CueToken::class)]
#[CoversClass(Region::class)]
#[CoversClass(SrtParser::class)]
#[CoversClass(WebVttParser::class)]
#[CoversClass(Timecode::class)]
#[CoversClass(Track::class)]
#[CoversClass(HtmlTranscriptReader::class)]
#[CoversClass(HtmlTranscriptWriter::class)]
#[CoversClass(JsonChapterReader::class)]
#[CoversClass(JsonTranscriptReader::class)]
#[CoversClass(SrtReader::class)]
#[CoversClass(SrtWriter::class)]
#[CoversClass(WebVttReader::class)]
final class BoundaryTest extends TestCase
{
    public function testAChapterChecksItsInstants(): void
    {
        self::assertSame(0, new Chapter(0, 't')->start);

        $refused = 0;

        foreach ([[-1, null], [Timecode::MAX + 1, null], [0, Timecode::MAX + 1]] as [$start, $end]) {
            try {
                new Chapter($start, 't', $end);
                self::fail('Built a chapter from '.$start.' and '.($end ?? 'null'));
            } catch (InvalidCue) {
                ++$refused;
            }
        }

        self::assertSame(3, $refused);
    }

    public function testAChapterListHasNoWaypointsByDefault(): void
    {
        self::assertFalse(new ChapterList()->waypoints);
    }

    public function testTheTableOfContentsIsAList(): void
    {
        $hidden = new Chapter(0, 'hidden', toc: false);
        $shown = new Chapter(1_000, 'shown');

        self::assertSame([$shown], new ChapterList([$hidden, $shown])->tableOfContents());
    }

    public function testACueCanBeRetimed(): void
    {
        $cue = new Cue(1, 2, 'a')->withTimes(3, 4);

        self::assertSame([3, 4], [$cue->start, $cue->end]);
    }

    public function testASettingIsNamedByItsFirstFortyCharacters(): void
    {
        $name = str_repeat('é', 50);

        try {
            new CueSettings([$name => $name]);
            self::fail('Built a cue setting from an unknown name.');
        } catch (InvalidCue $invalidCue) {
            self::assertSame('"'.str_repeat('é', 40).':'.str_repeat('é', 40).'" is not a WebVTT cue setting.', $invalidCue->getMessage());
        }
    }

    public function testARegionSettingIsNamedByItsFirstFortyCharacters(): void
    {
        $name = str_repeat('é', 50);

        try {
            new Region('r', [$name => $name]);
            self::fail('Built a region setting from an unknown name.');
        } catch (InvalidCue $invalidCue) {
            self::assertSame('"'.str_repeat('é', 40).':'.str_repeat('é', 40).'" is not a WebVTT region setting.', $invalidCue->getMessage());
        }
    }

    public function testARegionIdentifierMayHoldAColon(): void
    {
        self::assertSame('a:b', Region::parse(['id:a:b'])[0]?->id);
    }

    public function testARegionIdentifierIsTwoHundredCharactersAtMost(): void
    {
        $setting = 'id:'.str_repeat('a', 201);

        self::assertSame([null, [$setting]], Region::parse([$setting]));
        self::assertSame(str_repeat('a', 200), Region::parse(['id:'.str_repeat('a', 200)])[0]?->id);
    }

    public function testASpeakerIsListedOnceInOrder(): void
    {
        self::assertSame(['A', 'B'], CueText::voices('<v A>x<v A>y<v B>z'));
    }

    public function testAnUnknownTagWithADigitIsText(): void
    {
        self::assertSame('<b1>x', CueText::plain('<b1>x'));
    }

    public function testHtmlKeepsOnlyValidClassNames(): void
    {
        self::assertSame('<span class="ok">x</span>', CueText::toHtml('<c.a!b.ok>x</c>'));
        self::assertSame('<span class="loud" lang="en-CA">x</span>', CueText::toHtml('<lang.loud en-CA>x</lang>'));
    }

    public function testHtmlClosesWhatIsOpenInTheOrderOfNesting(): void
    {
        self::assertSame('<b><i>x</i></b>y', CueText::toHtml('<b><i>x</b>y'));
        self::assertSame('<b><i>x</i></b>', CueText::toHtml('<b><i>x'));
    }

    public function testHtmlWritesTheApostropheAsAnHtmlFiveReference(): void
    {
        self::assertSame('it&apos;s', CueText::toHtml("it's"));
        self::assertSame("<cite>O&apos;Neil:</cite>\n<time datetime=\"PT0S\">0:00</time>\n<p>it&apos;s</p>\n", new HtmlTranscriptWriter()->write(new Track([new Cue(0, 1, "<v O'Neil>it's")])));
    }

    public function testATranscriptWriterFollowsTheSpeaker(): void
    {
        $html = new HtmlTranscriptWriter()->write(new Track([new Cue(0, 1, '<v A>x'), new Cue(1, 2, '<v B>y'), new Cue(2, 3, '<v B>z')]));

        self::assertSame(1, substr_count($html, '<cite>A:'));
        self::assertSame(1, substr_count($html, '<cite>B:'));
    }

    public function testAnUnreadableTimeCodeIsRefusedWithoutBeingChecked(): void
    {
        $refused = 0;

        foreach ([-1, Timecode::MAX + 1] as $milliseconds) {
            try {
                Timecode::toSeconds($milliseconds);
                self::fail('Wrote '.$milliseconds.' ms.');
            } catch (InvalidCue) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testSecondsAreAnIntegerOnTheSecond(): void
    {
        self::assertSame(0, Timecode::toSeconds(0));
        self::assertSame(1, Timecode::toSeconds(1_000));
        self::assertSame(5, Timecode::toSeconds(5_000));
        self::assertSame(999, Timecode::toSeconds(999_000));
        self::assertSame(0.999, Timecode::toSeconds(999));
        self::assertSame(1.001, Timecode::toSeconds(1_001));
        self::assertSame(1.5, Timecode::toSeconds(1_500));
    }

    public function testAClockWritesItsMinutesAndSeconds(): void
    {
        self::assertSame('0:00', Timecode::formatClock(0));
        self::assertSame('0:59', Timecode::formatClock(59_000));
        self::assertSame('10:00', Timecode::formatClock(600_000));
        self::assertSame('1:59:59', Timecode::formatClock(7_199_000));
        self::assertSame('1:59:00', Timecode::formatClock(7_140_000));
        self::assertSame('2:00:00', Timecode::formatClock(7_200_000));
    }

    public function testTimestampsKeepTheirSeconds(): void
    {
        self::assertSame('00:00:30.000', Timecode::formatWebVtt(30_000));
        self::assertSame('00:01:59,500', Timecode::formatSrt(119_500));
    }

    public function testADurationStopsAtItsEnd(): void
    {
        self::assertSame(5_000, Timecode::parseDuration('PT5S'));
        self::assertNull(Timecode::parseDuration("PT5S\n"));
    }

    public function testAnInstantStaysOnTheScale(): void
    {
        self::assertSame(3_723_004, Timecode::parseWebVtt('01:02:03.004'));
        self::assertSame(123_004, Timecode::parseWebVtt('02:03.004'));
        self::assertSame(3_723_004, Timecode::parseSrt('01:02:03,004'));
        self::assertSame(3_723_040, Timecode::parseSrt('1:2:3.04'));
        self::assertSame(3_723_000, Timecode::parseClock('1:02:03'));
        self::assertSame(62_500, Timecode::parseClock('1:02,5'));
    }

    public function testAnExceptionOfTheLibraryHasNoCode(): void
    {
        self::assertSame(0, InvalidTimedText::at(1, 'x')->getCode());
        self::assertSame(0, new InvalidTimedText('x')->getCode());
    }

    public function testAHtmlTranscriptRemovesItsPrivateBreakMarkers(): void
    {
        $track = new HtmlTranscriptReader()->read("<p>a\u{E000}b</p>");

        self::assertSame(['ab'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testAHtmlTranscriptIsParsedInStandardsMode(): void
    {
        $track = new HtmlTranscriptReader()->read('<p>a<table><tr><td>b</td></tr></table></p>');

        self::assertSame(['a'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testATimeAtTheSameInstantAsTheOneAboveIsKept(): void
    {
        $inspection = new HtmlTranscriptReader()->inspect('<time>0:05</time><p>a</p><time>0:05</time><p>b</p>');

        self::assertSame([], $inspection->issues);
        self::assertSame([[5_000, 'a'], [5_000, 'b']], array_map(static fn (Cue $cue): array => [$cue->start, $cue->text], $inspection->track->cues));
    }

    public function testAParagraphOfLineBreaksOnlyIsEmpty(): void
    {
        $track = new HtmlTranscriptReader()->read('<p><br></p><p>x<br><br>y</p>');

        self::assertSame(["x\ny"], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testAnSrtCueMayBeAsShortAsAnInstantAndStartWhereTheLastStarted(): void
    {
        $inspection = new SrtReader()->inspect("1\n00:00:01,000 --> 00:00:01,000\na\n\n2\n00:00:01,000 --> 00:00:02,000\nb\n");

        self::assertSame([], $inspection->issues);
        self::assertSame([[1_000, 1_000], [1_000, 2_000]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $inspection->track->cues));
    }

    public function testAnSrtFileMayEndOnANumber(): void
    {
        $track = new SrtReader()->read("1\n00:00:01,000 --> 00:00:02,000\n3");

        self::assertSame(['3'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testAnSrtLineOfSpacesEndsACue(): void
    {
        $inspection = new SrtReader()->inspect("1\n00:00:01,000 --> 00:00:02,000\nHi\n  \nBye\n\n2\n00:00:03,000 --> 00:00:04,000\nthere\n");

        self::assertSame(['Hi', 'there'], array_map(static fn (Cue $cue): string => $cue->text, $inspection->track->cues));
        self::assertSame(['Line 5: A block without timings is ignored.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
    }

    public function testAnSrtLineOfSpacesEndsAnIgnoredBlock(): void
    {
        $track = new SrtReader()->inspect("garbage\n  \n1\n00:00:01,000 --> 00:00:02,000\nHi\n")->track;

        self::assertSame(['Hi'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
    }

    public function testAnSrtWriterDropsTheLinesOfSpaces(): void
    {
        self::assertSame("1\n00:00:00,000 --> 00:00:01,000\na\nb\n", new SrtWriter()->write(new Track([new Cue(0, 1_000, "a\n \nb")])));
    }

    public function testAWebVttNoteNeedsToOpenItsLine(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\nsee NOTE x\n00:00.000 --> 00:01.000\nHi\n");

        self::assertSame(['see NOTE x'], array_map(static fn (Cue $cue): ?string => $cue->id, $track->cues));
        self::assertSame([], $track->notes);
    }

    public function testAWebVttNoteKeepsItsFirstLine(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\nNOTE hello\nworld\n\n00:00.000 --> 00:01.000\nHi\n");

        self::assertSame(['hello'."\n".'world'], array_map(static fn (Note $note): string => $note->text, \array_slice($track->notes, 0, 1)));
    }

    public function testAWebVttDefinitionNeedsToOpenItsLine(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\nxSTYLE\n00:00.000 --> 00:01.000\nA\n\nSTYLED\n00:02.000 --> 00:03.000\nB\n");

        self::assertSame(['xSTYLE', 'STYLED'], array_map(static fn (Cue $cue): ?string => $cue->id, $track->cues));
        self::assertSame([], $track->styles);
    }

    public function testAWebVttStyleMayBeFollowedBySpaces(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\nSTYLE \t\n::cue { color: red }\n\n00:00.000 --> 00:01.000\nHi\n");

        self::assertSame(['::cue { color: red }'], $track->styles);
        self::assertSame([], $track->regions);
    }

    public function testAWebVttStyleAfterTheFirstCueIsIgnored(): void
    {
        $inspection = new WebVttReader(styles: StylePolicy::Keep)->inspect("WEBVTT\n\n00:00.000 --> 00:01.000\nHi\n\nSTYLE\n::cue { color: red }\n");

        self::assertSame([], $inspection->track->styles);
        self::assertSame(['Line 6: A STYLE block after the first cue is ignored.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
    }

    public function testAWebVttDefinitionThatHoldsAnArrowIsIgnored(): void
    {
        $inspection = new WebVttReader()->inspect("WEBVTT\n\nREGION\nid:r\n00:00.000 --> 00:01.000\nHi\n");

        self::assertSame([], $inspection->track->regions);
        self::assertSame(['Line 3: A REGION block holds "-->"; it is ignored.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
        self::assertCount(1, $inspection->track->cues);
    }

    public function testAWebVttCueWithoutTextEndsAtItsEmptyLine(): void
    {
        $inspection = new WebVttReader()->inspect("WEBVTT\n\n00:00.000 --> 00:01.000\n\nHi\n");

        self::assertSame([''], array_map(static fn (Cue $cue): string => $cue->text, $inspection->track->cues));
        self::assertSame(['Line 5: A block is neither a cue, a note, a style nor a region; it is ignored.'], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
    }

    public function testAWebVttCueMayHaveNoText(): void
    {
        $track = new WebVttReader()->read("WEBVTT\n\n00:00.000 --> 00:01.000\n\n00:02.000 --> 00:03.000\nB\n");

        self::assertSame([[0, ''], [2_000, 'B']], array_map(static fn (Cue $cue): array => [$cue->start, $cue->text], $track->cues));
    }

    public function testAWebVttCueMayStartWhereTheLastStartedAndLastNoTime(): void
    {
        $inspection = new WebVttReader()->inspect("WEBVTT\n\n00:01.000 --> 00:01.000\na\n\n00:01.000 --> 00:02.000\nb\n");

        self::assertSame([], $inspection->issues);
        self::assertSame([[1_000, 1_000], [1_000, 2_000]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $inspection->track->cues));
    }

    public function testAWebVttTimingLineIsReadWhole(): void
    {
        $inspection = new WebVttReader()->inspect("WEBVTT\n\nab 00:00.000 --> 00:01.000\nA\n\n00:02.000 --> 00:03.000x\nB\n");

        self::assertSame([], $inspection->track->cues);
        self::assertSame(
            ['Line 3: The timings of a cue are "start --> end", then its settings.', 'Line 6: The timings of a cue are "start --> end", then its settings.'],
            array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues),
        );
    }

    public function testAnIgnoredSettingIsNamedByItsFirstSixtyCharacters(): void
    {
        $setting = str_repeat('é', 70);
        $inspection = new WebVttReader()->inspect("WEBVTT\n\nREGION\nid:r ".$setting."\n\n00:00.000 --> 00:01.000 ".$setting."\nHi\n");

        self::assertSame(
            ['Line 3: "'.str_repeat('é', 60).'" is not a region setting; it is ignored.', 'Line 6: "'.str_repeat('é', 60).'" is not a cue setting; it is ignored.'],
            array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues),
        );
    }

    public function testAChapterMayStartWhereTheLastStartedAndEndWhereItStarted(): void
    {
        $list = new JsonChapterReader()->read('{"version":"1.2.0","chapters":[{"startTime":1,"endTime":1,"title":"a"},{"startTime":1,"title":"b"}]}');

        self::assertSame([1_000, 1_000], array_map(static fn (Chapter $chapter): int => $chapter->start, $list->chapters));
        self::assertSame(1_000, $list->chapters[0]->end);
    }

    public function testALocationNamesWhatIsWrongWithIt(): void
    {
        foreach ([
            ['{"name":5,"geo":"geo:1,2"}', 'Chapter 1: location "name" is a string.'],
            ['{"name":"x","geo":5}', 'Chapter 1: location "geo" is a string.'],
            ['{"name":"x","geo":"geo:1,2","osm":5}', 'Chapter 1: location "osm" is a string.'],
        ] as [$location, $message]) {
            try {
                new JsonChapterReader()->read('{"version":"1.2.0","chapters":[{"startTime":1,"title":"a","location":'.$location.'}]}');
                self::fail('Read: '.$message);
            } catch (InvalidTimedText $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testASegmentMayStartWhereTheLastStarted(): void
    {
        $inspection = new JsonTranscriptReader()->inspect('{"version":"1.0.0","segments":[{"startTime":1,"endTime":2,"body":"a"},{"startTime":1,"endTime":2,"body":"b"}]}');

        self::assertSame([], $inspection->issues);
        self::assertCount(2, $inspection->track->cues);
    }

    public function testAMergeKeepsEveryCueNoteAndStyle(): void
    {
        $a = new Track([new Cue(0, 1, 'a1'), new Cue(2, 3, 'a2')], [], ['s1', 's2'], [new Note('n1'), new Note('n2')]);
        $b = new Track([new Cue(4, 5, 'b1'), new Cue(6, 7, 'b2')], [], ['s3', 's4'], [new Note('n3'), new Note('n4')]);
        $merged = $a->mergedWith($b);

        self::assertSame(['a1', 'a2', 'b1', 'b2'], array_map(static fn (Cue $cue): string => $cue->text, $merged->cues));
        self::assertSame(['n1', 'n2', 'n3', 'n4'], array_map(static fn (Note $note): string => $note->text, $merged->notes));
        self::assertSame(['s1', 's2', 's3', 's4'], $merged->styles);
    }

    public function testTheNotesBeforeASplitAreAList(): void
    {
        $track = new Track([new Cue(0, 1_000, 'a')], notes: [new Note('late', 5_000), new Note('early', 500)]);

        self::assertSame(['early'], array_map(static fn (Note $note): string => $note->text, $track->splitAt(1_000)[0]->notes));
        self::assertSame([0], array_keys($track->splitAt(1_000)[0]->notes));
    }
}
