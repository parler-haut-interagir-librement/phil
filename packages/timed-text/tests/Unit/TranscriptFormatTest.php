<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Cue;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Html\HtmlTranscriptReader;
use Phil\TimedText\Html\HtmlTranscriptWriter;
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\Podcast\JsonTranscriptWriter;
use Phil\TimedText\Srt\SrtReader;
use Phil\TimedText\Srt\SrtWriter;
use Phil\TimedText\Track;
use Phil\TimedText\TranscriptFormat;
use Phil\TimedText\WebVtt\WebVttReader;
use Phil\TimedText\WebVtt\WebVttWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranscriptFormat::class)]
final class TranscriptFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?TranscriptFormat}>
     */
    public static function mediaTypes(): iterable
    {
        yield 'WebVTT' => ['text/vtt', TranscriptFormat::WebVtt];
        yield 'SRT' => ['application/srt', TranscriptFormat::Srt];
        yield 'SubRip' => ['application/x-subrip', TranscriptFormat::Srt];
        yield 'text SRT' => ['Text/SRT', TranscriptFormat::Srt];
        yield 'JSON with a charset' => [' application/json; charset=utf-8', TranscriptFormat::Json];
        yield 'HTML' => ['text/html', TranscriptFormat::Html];
        yield 'plain text' => ['text/plain', null];
    }

    #[DataProvider('mediaTypes')]
    public function testItKnowsTheMediaTypes(string $mediaType, ?TranscriptFormat $format): void
    {
        self::assertSame($format, TranscriptFormat::fromMediaType($mediaType));
    }

    public function testEachFormatHasItsExtensionReaderAndWriter(): void
    {
        self::assertSame(['vtt', 'srt', 'json', 'html'], array_map(static fn (TranscriptFormat $format): string => $format->extension(), TranscriptFormat::cases()));
        self::assertSame(
            [WebVttReader::class, SrtReader::class, JsonTranscriptReader::class, HtmlTranscriptReader::class],
            array_map(static fn (TranscriptFormat $format): string => $format->reader()::class, TranscriptFormat::cases()),
        );
        self::assertSame(
            [WebVttWriter::class, SrtWriter::class, JsonTranscriptWriter::class, HtmlTranscriptWriter::class],
            array_map(static fn (TranscriptFormat $format): string => $format->writer()::class, TranscriptFormat::cases()),
        );
    }

    public function testTheReadersTakeTheLimitsGiven(): void
    {
        $track = new Track([new Cue(0, 1_000, 'a'), new Cue(1_000, 2_000, 'b')]);

        foreach (TranscriptFormat::cases() as $format) {
            $written = $format->writer()->write($track);

            self::assertCount(2, $format->reader()->read($written)->cues, $format->name);

            try {
                $format->reader(new Limits(maxCues: 1))->read($written);
                self::fail($format->name.' read past the limits.');
            } catch (LimitExceeded $exception) {
                self::assertStringStartsWith('The document holds more than 1 ', $exception->getMessage());
            }
        }
    }

    public function testATrackCrossesEveryFormat(): void
    {
        $track = new Track([new Cue(500, 2_000, '<v Alice>Hello &amp; welcome'), new Cue(2_000, 3_250, '<v Bob>Bye')]);

        foreach ([TranscriptFormat::WebVtt, TranscriptFormat::Json] as $format) {
            self::assertEquals($track, $format->reader()->read($format->writer()->write($track)), $format->name);
        }

        $srt = TranscriptFormat::Srt->reader()->read(TranscriptFormat::Srt->writer()->write($track));
        self::assertSame(['Hello &amp; welcome', 'Bye'], array_map(static fn (Cue $cue): string => $cue->text, $srt->cues));
    }
}
