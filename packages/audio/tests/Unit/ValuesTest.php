<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit;

use Phil\Audio\AudioFormat;
use Phil\Audio\AudioMetadata;
use Phil\Audio\CommandResult;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Excerpt;
use Phil\Audio\ListeningMode;
use Phil\Audio\ListeningPolicy;
use Phil\Audio\Rendition;
use Phil\TimedText\Timecode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AudioFormat::class)]
#[CoversClass(Rendition::class)]
#[CoversClass(Excerpt::class)]
#[CoversClass(ListeningPolicy::class)]
#[CoversClass(AudioMetadata::class)]
#[CoversClass(CommandResult::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?AudioFormat}>
     */
    public static function mediaTypes(): iterable
    {
        yield 'mpeg' => ['audio/mpeg', AudioFormat::Mp3];
        yield 'mp3 alias, upper case' => ['Audio/MP3', AudioFormat::Mp3];
        yield 'mp4' => ['audio/mp4', AudioFormat::Aac];
        yield 'aac' => ['audio/aac', AudioFormat::Aac];
        yield 'm4a' => ['audio/x-m4a', AudioFormat::Aac];
        yield 'ogg' => ['audio/ogg', AudioFormat::Opus];
        yield 'ogg opus' => ['audio/ogg; codecs=opus', AudioFormat::Opus];
        yield 'ogg opus quoted' => ['audio/ogg; charset=x; codecs="opus"', AudioFormat::Opus];
        yield 'ogg vorbis' => ['audio/ogg; codecs=vorbis', null];
        yield 'ogg codecs empty' => ['audio/ogg;codecs', null];
        yield 'opus' => ['audio/opus', AudioFormat::Opus];
        yield 'flac' => ['audio/flac', AudioFormat::Flac];
        yield 'x-flac' => ['audio/x-flac', AudioFormat::Flac];
        yield 'wav' => [' audio/wav ', AudioFormat::Wav];
        yield 'x-wav' => ['audio/x-wav', AudioFormat::Wav];
        yield 'wave' => ['audio/wave', AudioFormat::Wav];
        yield 'video' => ['video/mp4', null];
    }

    #[DataProvider('mediaTypes')]
    public function testAFormatIsFoundByItsMediaType(string $mediaType, ?AudioFormat $format): void
    {
        self::assertSame($format, AudioFormat::fromMediaType($mediaType));
    }

    public function testEachFormatHasItsMediaTypeAndExtension(): void
    {
        $described = array_map(static fn (AudioFormat $format): array => [$format->mediaType(), $format->extension(), $format->isLossless()], AudioFormat::cases());

        self::assertSame([
            ['audio/mpeg', 'mp3', false],
            ['audio/mp4', 'm4a', false],
            ['audio/ogg; codecs=opus', 'opus', false],
            ['audio/flac', 'flac', true],
            ['audio/wav', 'wav', true],
        ], $described);

        foreach (AudioFormat::cases() as $format) {
            self::assertSame($format, AudioFormat::fromMediaType($format->mediaType()));
        }
    }

    public function testARenditionKeepsWhatItIsAsked(): void
    {
        $rendition = new Rendition(AudioFormat::Opus, 8, 1, 48_000);
        self::assertSame([AudioFormat::Opus, 8, 1, 48_000], [$rendition->format, $rendition->bitrate, $rendition->channels, $rendition->sampleRate]);

        $rendition = new Rendition(AudioFormat::Mp3, 512, 2, 44_100);
        self::assertSame([512, 2, 44_100], [$rendition->bitrate, $rendition->channels, $rendition->sampleRate]);

        $lossless = new Rendition(AudioFormat::Flac, channels: 2, sampleRate: 8_000);
        self::assertNull($lossless->bitrate);
        self::assertNull(new Rendition(AudioFormat::Wav)->channels);
    }

    /**
     * @return iterable<string, array{AudioFormat, ?int, ?int, ?int, string}>
     */
    public static function badRenditions(): iterable
    {
        yield 'lossless with a bitrate' => [AudioFormat::Flac, 128, null, null, 'The lossless format "flac" takes no bitrate.'];
        yield 'bitrate too low' => [AudioFormat::Mp3, 7, null, null, 'A bitrate is from 8 to 512 kbit/s, not 7.'];
        yield 'bitrate too high' => [AudioFormat::Mp3, 513, null, null, 'A bitrate is from 8 to 512 kbit/s, not 513.'];
        yield 'no channel' => [AudioFormat::Mp3, null, 0, null, 'A rendition has 1 or 2 channels, not 0.'];
        yield 'three channels' => [AudioFormat::Mp3, null, 3, null, 'A rendition has 1 or 2 channels, not 3.'];
        yield 'odd rate' => [AudioFormat::Mp3, null, null, 44_000, '44000 Hz is not a sample rate of the format "mp3".'];
        yield 'rate opus refuses' => [AudioFormat::Opus, null, null, 44_100, '44100 Hz is not a sample rate of the format "opus".'];
    }

    #[DataProvider('badRenditions')]
    public function testARenditionRefusesWhatNoEncoderTakes(AudioFormat $format, ?int $bitrate, ?int $channels, ?int $sampleRate, string $message): void
    {
        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Rendition($format, $bitrate, $channels, $sampleRate);
    }

    public function testAnExcerptEndsAfterItsDurationAndIsCutToAFile(): void
    {
        $excerpt = new Excerpt(30_000, 45_000);

        self::assertSame(75_000, $excerpt->end());
        self::assertSame($excerpt, $excerpt->within(75_000));
        self::assertSame($excerpt, $excerpt->within(90_000));
        $cut = $excerpt->within(60_000);
        self::assertNotNull($cut);
        self::assertSame([30_000, 30_000], [$cut->start, $cut->duration]);
        $last = $excerpt->within(30_001);
        self::assertNotNull($last);
        self::assertSame(1, $last->duration);
        self::assertNull($excerpt->within(30_000));
        self::assertNull($excerpt->within(10_000));
        self::assertSame(Timecode::MAX, new Excerpt(0, Timecode::MAX)->end());
        self::assertSame(Timecode::MAX, new Excerpt(Timecode::MAX - 1, 1)->end());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badExcerpts(): iterable
    {
        yield 'negative start' => [-1, 1_000];
        yield 'no duration' => [0, 0];
        yield 'past the last instant' => [1, Timecode::MAX];
    }

    #[DataProvider('badExcerpts')]
    public function testAnExcerptIsWithinTheInstantsOfTimedText(int $start, int $duration): void
    {
        $this->expectException(InvalidAudio::class);

        new Excerpt($start, $duration);
    }

    public function testAPolicySaysWhatAListenerHears(): void
    {
        $excerpt = new Excerpt(0, 30_000);

        self::assertSame(ListeningMode::Full, new ListeningPolicy()->mode);
        self::assertNull(ListeningPolicy::full()->excerpt);
        self::assertSame(ListeningMode::Full, ListeningPolicy::full()->forListener(false));
        self::assertSame(ListeningMode::Excerpt, ListeningPolicy::excerpt($excerpt)->forListener(false));
        self::assertSame($excerpt, ListeningPolicy::excerpt($excerpt)->excerpt);
        self::assertSame(ListeningMode::PurchaseOnly, ListeningPolicy::purchaseOnly()->forListener(false));
        self::assertSame(ListeningMode::Full, ListeningPolicy::purchaseOnly()->forListener(true));
        self::assertSame(ListeningMode::Full, ListeningPolicy::excerpt($excerpt)->forListener(true));
    }

    public function testAnExcerptGoesWithTheExcerptModeOnly(): void
    {
        try {
            new ListeningPolicy(ListeningMode::Excerpt);
            self::fail('An excerpt mode without an excerpt is refused.');
        } catch (InvalidAudio $invalidAudio) {
            self::assertSame('An excerpt is given with the excerpt mode, and only with it.', $invalidAudio->getMessage());
        }

        $this->expectException(InvalidAudio::class);
        new ListeningPolicy(ListeningMode::Full, new Excerpt(0, 1));
    }

    public function testMetadataKeepsWhatAToolRead(): void
    {
        $metadata = new AudioMetadata(1_000, 'mp3', 'mp3', 128_000, 44_100, 2, ['title' => 'Episode']);

        self::assertSame('Episode', $metadata->tag('TITLE'));
        self::assertNull($metadata->tag('artist'));
        self::assertSame([1_000, 'mp3', 'mp3', 128_000, 44_100, 2], [$metadata->duration, $metadata->container, $metadata->codec, $metadata->bitrate, $metadata->sampleRate, $metadata->channels]);

        $unknown = new AudioMetadata(0, 'wav', bitrate: 0, sampleRate: 1, channels: 1);
        self::assertSame([0, 0, 1, 1], [$unknown->duration, $unknown->bitrate, $unknown->sampleRate, $unknown->channels]);
        self::assertNull(new AudioMetadata(null, 'wav')->duration);
    }

    /**
     * @return iterable<string, array{?int, ?int, ?int, ?int}>
     */
    public static function badMetadata(): iterable
    {
        yield 'negative duration' => [-1, null, null, null];
        yield 'negative bitrate' => [null, -1, null, null];
        yield 'no sample rate' => [null, null, 0, null];
        yield 'no channel' => [null, null, null, 0];
    }

    #[DataProvider('badMetadata')]
    public function testMetadataRefusesImpossibleNumbers(?int $duration, ?int $bitrate, ?int $sampleRate, ?int $channels): void
    {
        $this->expectException(InvalidAudio::class);

        new AudioMetadata($duration, 'mp3', null, $bitrate, $sampleRate, $channels);
    }

    public function testACommandSucceedsWithTheExitCodeZero(): void
    {
        self::assertTrue(new CommandResult(0)->isSuccessful());
        self::assertFalse(new CommandResult(1, 'out', 'err')->isSuccessful());
        self::assertSame(['', ''], [new CommandResult(0)->output, new CommandResult(0)->errorOutput]);
    }
}
