<?php

declare(strict_types=1);

namespace Phil\Transcription\Tests\Unit;

use Phil\Transcription\Exception\LimitExceeded;
use Phil\Transcription\Exception\TranscriptionFailed;
use Phil\Transcription\Internal\Segments;
use Phil\Transcription\Language;
use Phil\Transcription\Reader\DeepgramReader;
use Phil\Transcription\Reader\VerboseJsonReader;
use Phil\Transcription\ReviewState;
use Phil\Transcription\Segment;
use Phil\Transcription\TranscriptionLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerboseJsonReader::class)]
#[CoversClass(DeepgramReader::class)]
#[CoversClass(Segments::class)]
final class ReaderTest extends TestCase
{
    public function testAWhisperResponseBecomesSegmentsInTheDeclaredLanguage(): void
    {
        $transcript = new VerboseJsonReader()->read((string) file_get_contents(__DIR__.'/../Fixtures/verbose.json'), new Language('fr-CA'), 'whisper.cpp');

        self::assertSame('fr-CA', $transcript->language->tag);
        self::assertSame(ReviewState::ToReview, $transcript->review);
        self::assertSame('whisper.cpp', $transcript->engine);
        self::assertCount(2, $transcript->segments);
        self::assertSame([0, 2_480, 'Bonjour et bienvenue.'], [$transcript->segments[0]->start, $transcript->segments[0]->end, $transcript->segments[0]->text]);
        self::assertSame([2_480, 7_500, "Aujourd'hui, on parle de jeux & d'<humour>."], [$transcript->segments[1]->start, $transcript->segments[1]->end, $transcript->segments[1]->text]);
        self::assertNull($transcript->segments[0]->speaker);
        self::assertNull($transcript->segments[0]->confidence);
        self::assertNull(new VerboseJsonReader()->read('{"segments":[]}', new Language('fr'))->engine);
    }

    public function testAnUnusableSegmentIsSkipped(): void
    {
        $json = json_encode(['segments' => [
            'not an object',
            ['start' => 0, 'end' => 1, 'text' => ' kept '],
            ['start' => '0', 'end' => 1, 'text' => 'start as text'],
            ['start' => 0, 'end' => null, 'text' => 'no end'],
            ['start' => -0.001, 'end' => 1, 'text' => 'negative'],
            ['start' => 2, 'end' => 1, 'text' => 'backward'],
            ['start' => 0, 'end' => 36_000_000, 'text' => 'past the end'],
            ['start' => 0, 'end' => 1, 'text' => " \u{00A0} "],
            ['start' => 0, 'end' => 1, 'text' => 5],
            ['start' => 0, 'end' => 1],
            ['start' => 1.0004, 'end' => 1.0004, 'text' => 'instant'],
            ['start' => 0, 'end' => 35_999_999.999, 'text' => 'last instant'],
        ]], \JSON_THROW_ON_ERROR);
        $segments = new VerboseJsonReader()->read($json, new Language('en'))->segments;

        self::assertSame(['kept', 'instant', 'last instant'], array_map(static fn (Segment $segment): string => $segment->text, $segments));
        self::assertSame([1_000, 1_000], [$segments[0]->end, $segments[1]->start]);
        self::assertSame(35_999_999_999, $segments[2]->end);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badWhisperResponses(): iterable
    {
        yield 'not JSON' => ['<html>', 'The response is no JSON: Syntax error'];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), 'The response is no JSON: Maximum stack depth exceeded'];
        yield 'a string' => ['"text"', 'The response is a JSON object.'];
        yield 'text only' => ['{"text":"Bonjour"}', 'The response holds no list of segments.'];
        yield 'segments an object' => ['{"segments":{"a":{}}}', 'The response holds no list of segments.'];
    }

    #[DataProvider('badWhisperResponses')]
    public function testAWhisperResponseWithoutSegmentsFails(string $json, string $message): void
    {
        $this->expectException(TranscriptionFailed::class);
        $this->expectExceptionMessageIsOrContains($message);

        new VerboseJsonReader()->read($json, new Language('en'));
    }

    public function testTheLimitsOfAResponseAreKept(): void
    {
        $json = '{"segments":[{"start":0,"end":1,"text":"abcd"},{"start":1,"end":2,"text":"efgh"}]}';
        self::assertCount(2, new VerboseJsonReader(new TranscriptionLimits(maxResponseBytes: \strlen($json), maxSegments: 2, maxTextBytes: 4))->read($json, new Language('en'))->segments);

        foreach ([
            [new TranscriptionLimits(maxResponseBytes: \strlen($json) - 1), \sprintf('The response holds more than %d bytes.', \strlen($json) - 1)],
            [new TranscriptionLimits(maxSegments: 1), 'The transcript holds more than 1 segments.'],
            [new TranscriptionLimits(maxTextBytes: 3), 'A segment holds more than 3 bytes of text.'],
        ] as [$limits, $message]) {
            try {
                new VerboseJsonReader($limits)->read($json, new Language('en'));
                self::fail('A response past its limits is refused.');
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testADeepgramResponseNamesItsSpeakers(): void
    {
        $transcript = new DeepgramReader()->read((string) file_get_contents(__DIR__.'/../Fixtures/deepgram.json'), new Language('fr-CA'), 'deepgram:nova-3');

        self::assertSame('deepgram:nova-3', $transcript->engine);
        self::assertSame([80, 1_600, 'Bonjour.', 'Speaker 1', 0.991], [$transcript->segments[0]->start, $transcript->segments[0]->end, $transcript->segments[0]->text, $transcript->segments[0]->speaker, $transcript->segments[0]->confidence]);
        self::assertSame(['Speaker 2', 0.87], [$transcript->segments[1]->speaker, $transcript->segments[1]->confidence]);
        self::assertSame('Voix 1', new DeepgramReader(speakerLabel: 'Voix %d')->read((string) file_get_contents(__DIR__.'/../Fixtures/deepgram.json'), new Language('fr'))->segments[0]->speaker);
    }

    public function testAnUnknownSpeakerOrConfidenceIsLeftOut(): void
    {
        $json = json_encode(['results' => ['utterances' => [
            ['start' => 0, 'end' => 1, 'transcript' => 'a', 'speaker' => -1, 'confidence' => 1.5],
            ['start' => 0, 'end' => 1, 'transcript' => 'b', 'speaker' => 1_000, 'confidence' => -0.1],
            ['start' => 0, 'end' => 1, 'transcript' => 'c', 'speaker' => '1', 'confidence' => '0.5'],
            ['start' => 0, 'end' => 1, 'transcript' => 'd', 'speaker' => 999, 'confidence' => 1],
            ['start' => 0, 'end' => 1, 'transcript' => 'e', 'confidence' => 0],
            'x',
        ]]], \JSON_THROW_ON_ERROR);
        $segments = new DeepgramReader()->read($json, new Language('en'))->segments;

        self::assertSame([null, null, null, 'Speaker 1000', null], array_map(static fn (Segment $segment): ?string => $segment->speaker, $segments));
        self::assertSame([null, null, null, 1.0, 0.0], array_map(static fn (Segment $segment): ?float => $segment->confidence, $segments));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badDeepgramResponses(): iterable
    {
        yield 'no results' => ['{}'];
        yield 'results a list' => ['{"results":[1]}'];
        yield 'no utterances' => ['{"results":{"channels":[]}}'];
        yield 'utterances an object' => ['{"results":{"utterances":{"a":1}}}'];
    }

    #[DataProvider('badDeepgramResponses')]
    public function testADeepgramResponseWithoutUtterancesFails(string $json): void
    {
        $this->expectException(TranscriptionFailed::class);
        $this->expectExceptionMessageIsOrContains('The response holds no list of utterances.');

        new DeepgramReader()->read($json, new Language('en'));
    }

    public function testADeepgramResponseIsBounded(): void
    {
        $this->expectException(LimitExceeded::class);

        new DeepgramReader(new TranscriptionLimits(maxResponseBytes: 5))->read('{"results":{"utterances":[]}}', new Language('en'));
    }

    public function testAnIntegerConfidenceIsAFloat(): void
    {
        $segments = new Segments(new TranscriptionLimits());
        $segments->add(0, 1, 'a', null, 1);
        $segments->add(0, 1, 'b', null, 0);

        self::assertSame([1.0, 0.0], array_map(static fn (Segment $segment): ?float => $segment->confidence, $segments->all()));
    }

    public function testAnInstantIsRoundedToTheNearestMillisecond(): void
    {
        $segments = new Segments(new TranscriptionLimits());
        $segments->add(0.0006, 1.0004, 'a');

        self::assertSame([1, 1_000], [$segments->all()[0]->start, $segments->all()[0]->end]);
    }

    public function testAResponseNestedAtItsDepthIsDecodedAndOneDeeperIsRefused(): void
    {
        $response = static fn (int $depth): string => '{"x":'.str_repeat('[', $depth).str_repeat(']', $depth).'}';
        $segments = new Segments(new TranscriptionLimits());

        self::assertCount(1, $segments->decode($response(14)));

        $this->expectException(TranscriptionFailed::class);
        $this->expectExceptionMessageIsOrContains('The response is no JSON: Maximum stack depth exceeded');

        $segments->decode($response(15));
    }
}
