<?php

declare(strict_types=1);

namespace Phil\Transcription\Tests\Unit;

use Phil\TimedText\Cue;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;
use Phil\TimedText\TranscriptFormat;
use Phil\Transcription\Exception\ConsentRequired;
use Phil\Transcription\Exception\InvalidTranscription;
use Phil\Transcription\Exception\NotReviewed;
use Phil\Transcription\Language;
use Phil\Transcription\Processing;
use Phil\Transcription\RecordingConsent;
use Phil\Transcription\ReviewState;
use Phil\Transcription\Segment;
use Phil\Transcription\Transcript;
use Phil\Transcription\TranscriptionLimits;
use Phil\Transcription\TranscriptionRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Language::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Transcript::class)]
#[CoversClass(TranscriptionRequest::class)]
#[CoversClass(TranscriptionLimits::class)]
final class ModelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function languages(): iterable
    {
        yield 'Canadian French' => ['fr-CA', 'fr-CA', 'fr'];
        yield 'lower case region' => ['fr-ca', 'fr-CA', 'fr'];
        yield 'underscore' => ['FR_ca', 'fr-CA', 'fr'];
        yield 'language alone' => ['EN', 'en', 'en'];
        yield 'three letters' => ['haw', 'haw', 'haw'];
        yield 'script and region' => ['zh-hant-tw', 'zh-Hant-TW', 'zh'];
        yield 'script alone' => ['sr-LATN', 'sr-Latn', 'sr'];
        yield 'numeric region' => ['es-419', 'es-419', 'es'];
    }

    #[DataProvider('languages')]
    public function testALanguageIsWrittenInItsUsualCase(string $text, string $tag, string $primary): void
    {
        $language = new Language($text);

        self::assertSame($tag, $language->tag);
        self::assertSame($tag, (string) $language);
        self::assertSame($primary, $language->primary());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLanguages(): iterable
    {
        yield 'empty' => [''];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'a word' => ['french'];
        yield 'long region' => ['fr-CAN'];
        yield 'trailing dash' => ['fr-'];
        yield 'a line after' => ["fr\n"];
        yield 'text before' => ['x fr'];
    }

    #[DataProvider('notLanguages')]
    public function testALanguageIsATag(string $text): void
    {
        $this->expectException(InvalidTranscription::class);
        $this->expectExceptionMessageIsOrContains('is no language tag of a language, a script and a region.');

        new Language($text);
    }

    public function testASegmentIsOneLineOfText(): void
    {
        $segment = new Segment(0, 0, "  Hello\n\tthere ", " Ada\nL. ", 0.0);

        self::assertSame('Hello there', $segment->text);
        self::assertSame('Ada L.', $segment->speaker);
        self::assertSame(0.0, $segment->confidence);
        self::assertNull(new Segment(0, 1, 'a', ' ')->speaker);
        self::assertNull(new Segment(0, 1, 'a')->speaker);
        self::assertSame(1.0, new Segment(1, Timecode::MAX, 'a', confidence: 1.0)->confidence);
    }

    /**
     * @return iterable<string, array{int, int, string, ?float}>
     */
    public static function badSegments(): iterable
    {
        yield 'negative' => [-1, 1, 'a', null];
        yield 'backward' => [2, 1, 'a', null];
        yield 'past the end' => [0, Timecode::MAX + 1, 'a', null];
        yield 'blank' => [0, 1, " \n\u{00A0}", null];
        yield 'confidence below' => [0, 1, 'a', -0.01];
        yield 'confidence above' => [0, 1, 'a', 1.01];
    }

    #[DataProvider('badSegments')]
    public function testASegmentRefusesWhatNoCueCarries(int $start, int $end, string $text, ?float $confidence): void
    {
        $this->expectException(InvalidTranscription::class);

        new Segment($start, $end, $text, null, $confidence);
    }

    public function testATranscriptIsToReviewUntilAPersonReadsIt(): void
    {
        $transcript = new Transcript(new Language('fr-CA'), [new Segment(0, 1_000, 'Bonjour & <bienvenue>', 'Ada'), new Segment(1_000, 2_000, '--> suite')], engine: 'test');

        self::assertSame(ReviewState::ToReview, $transcript->review);
        self::assertFalse($transcript->isPublishable());
        self::assertSame("Bonjour & <bienvenue>\n--> suite", $transcript->text());

        try {
            $transcript->publish(TranscriptFormat::WebVtt);
            self::fail('A transcript to review is not published.');
        } catch (NotReviewed $notReviewed) {
            self::assertSame('A transcript is reviewed by a person before it is published.', $notReviewed->getMessage());
        }

        $reviewed = $transcript->reviewed();
        self::assertTrue($reviewed->isPublishable());
        self::assertSame('test', $reviewed->engine);
        self::assertSame($transcript->segments, $reviewed->segments);
        self::assertSame($transcript->language, $reviewed->language);
        self::assertSame("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\n<v Ada>Bonjour &amp; &lt;bienvenue&gt;\n\n00:00:01.000 --> 00:00:02.000\n--&gt; suite\n", $reviewed->publish(TranscriptFormat::WebVtt));
        self::assertStringContainsString('"speaker": "Ada"', $reviewed->publish(TranscriptFormat::Json));

        $edited = $reviewed->withSegments([new Segment(0, 500, 'Bonjour')]);
        self::assertSame(ReviewState::ToReview, $edited->review);
        self::assertSame('test', $edited->engine);
        self::assertSame('Bonjour', $edited->text());
        self::assertSame('fr-CA', $edited->language->tag);
    }

    public function testATrackAnEditorCorrectedBecomesATranscriptAgain(): void
    {
        $track = new Track([new Cue(0, 900, '<v Ada>Bonjour &amp; salut'), new Cue(900, 1_000, '<i> </i>'), new Cue(1_000, 2_000, 'Merci')]);
        $transcript = Transcript::fromTrack($track, new Language('fr'), ReviewState::Reviewed, 'editor');

        self::assertCount(2, $transcript->segments);
        self::assertSame(['Bonjour & salut', 'Ada', 0, 900], [$transcript->segments[0]->text, $transcript->segments[0]->speaker, $transcript->segments[0]->start, $transcript->segments[0]->end]);
        self::assertNull($transcript->segments[1]->speaker);
        self::assertSame(ReviewState::Reviewed, $transcript->review);
        self::assertSame('editor', $transcript->engine);
        self::assertEquals($track->cues[0], $transcript->toTrack()->cues[0]);
        self::assertSame(ReviewState::ToReview, Transcript::fromTrack($track, new Language('fr'))->review);
        self::assertNull(Transcript::fromTrack($track, new Language('fr'))->engine);
    }

    public function testARequestCarriesTheConsentOfThePeopleHeard(): void
    {
        $local = new TranscriptionRequest('/a.wav', new Language('fr-CA'));
        self::assertSame(RecordingConsent::LocalOnly, $local->consent);
        self::assertTrue($local->allows(Processing::Local));
        self::assertFalse($local->allows(Processing::Cloud));
        $local->assertAllows(Processing::Local);

        $cloud = new TranscriptionRequest('/a.wav', new Language('fr-CA'), RecordingConsent::Cloud, str_repeat('a', TranscriptionRequest::MAX_PROMPT_BYTES));
        self::assertTrue($cloud->allows(Processing::Cloud));
        self::assertTrue($cloud->allows(Processing::Local));
        $cloud->assertAllows(Processing::Cloud);

        $this->expectException(ConsentRequired::class);
        $this->expectExceptionMessageIsOrContains('The people heard in this recording did not agree that it leaves the site: only a local transcriber may transcribe it.');
        $local->assertAllows(Processing::Cloud);
    }

    public function testARequestNamesAFileAndAShortHint(): void
    {
        try {
            new TranscriptionRequest('', new Language('fr'));
            self::fail('A request without a file is refused.');
        } catch (InvalidTranscription $invalidTranscription) {
            self::assertSame('A request names the file of its recording.', $invalidTranscription->getMessage());
        }

        $this->expectException(InvalidTranscription::class);
        $this->expectExceptionMessageIsOrContains('A hint holds 1000 bytes at most.');
        new TranscriptionRequest('/a.wav', new Language('fr'), prompt: str_repeat('a', TranscriptionRequest::MAX_PROMPT_BYTES + 1));
    }

    /**
     * @return iterable<string, array{int, int, int, int, float}>
     */
    public static function badLimits(): iterable
    {
        yield 'audio' => [0, 1, 1, 1, 1.0];
        yield 'response' => [1, 0, 1, 1, 1.0];
        yield 'segments' => [1, 1, 0, 1, 1.0];
        yield 'text' => [1, 1, 1, 0, 1.0];
        yield 'time' => [1, 1, 1, 1, 0.0];
    }

    #[DataProvider('badLimits')]
    public function testEveryLimitIsPositive(int $audio, int $response, int $segments, int $text, float $timeout): void
    {
        $this->expectException(InvalidTranscription::class);
        $this->expectExceptionMessageIsOrContains('Every limit of a transcription is positive.');

        new TranscriptionLimits($audio, $response, $segments, $text, $timeout);
    }

    public function testTheDefaultLimitsCoverAFewHours(): void
    {
        $limits = new TranscriptionLimits();

        self::assertSame([1_073_741_824, 16_777_216, 50_000, 8_192, 3_600.0], [$limits->maxAudioBytes, $limits->maxResponseBytes, $limits->maxSegments, $limits->maxTextBytes, $limits->timeout]);
        self::assertSame(0.5, new TranscriptionLimits(1, 1, 1, 1, 0.5)->timeout);
    }
}
