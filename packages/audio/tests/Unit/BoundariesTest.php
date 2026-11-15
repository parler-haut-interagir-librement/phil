<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit;

use Phil\Audio\AudioFormat;
use Phil\Audio\AudioTranscript;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Ffmpeg\FfprobeReader;
use Phil\Audio\Peaks;
use Phil\Audio\PeaksComputer;
use Phil\Audio\PeaksReader;
use Phil\Audio\Tests\Support\StallingStream;
use Phil\Audio\Tests\Support\TrickleStream;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of what the readers and the values accept: one more, one less, a line feed after the end.
 */
#[CoversClass(AudioFormat::class)]
#[CoversClass(AudioTranscript::class)]
#[CoversClass(FfprobeReader::class)]
#[CoversClass(Peaks::class)]
#[CoversClass(PeaksComputer::class)]
#[CoversClass(PeaksReader::class)]
final class BoundariesTest extends TestCase
{
    public function testAParameterOfAMediaTypeKeepsItsOwnEqualSigns(): void
    {
        self::assertNull(AudioFormat::fromMediaType('audio/ogg; codecs=opus=x'));
        self::assertSame(AudioFormat::Opus, AudioFormat::fromMediaType('audio/ogg; codecs="opus"'));
    }

    public function testALanguageTagEndsWithTheTag(): void
    {
        $this->expectException(InvalidAudio::class);

        new AudioTranscript(new Track(), "en\n", 'English');
    }

    public function testTheDurationIsRoundedToTheNearestMillisecond(): void
    {
        self::assertSame(1_000, new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"1.0004"}]}')->duration);
    }

    public function testANumberEndsWithItsDigits(): void
    {
        self::assertNull(new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"1.5\\n"}]}')->duration);
    }

    public function testAReportNestedAtItsDepthIsReadAndOneDeeperIsRefused(): void
    {
        $report = static fn (int $depth): string => '{"streams":[{"codec_type":"audio"}],"extra":'.str_repeat('[', $depth).str_repeat(']', $depth).'}';

        self::assertSame('unknown', new FfprobeReader()->read($report(14))->container);

        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains('The report of ffprobe is no JSON: Maximum stack depth exceeded');

        new FfprobeReader()->read($report(15));
    }

    public function testADocumentOfPeaksNestedOneLevelDeeperThanItsDataIsRefused(): void
    {
        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains('The peaks are no JSON: Maximum stack depth exceeded');

        new PeaksReader()->read('{"data":[[1]]}');
    }

    public function testADocumentHoldsAsManyRunsAsItIsGivenAndNoMore(): void
    {
        $document = static fn (int $runs): string => json_encode(['version' => 2, 'channels' => 1, 'sample_rate' => 1, 'samples_per_pixel' => 1, 'bits' => 8, 'length' => $runs, 'data' => array_fill(0, 2 * $runs, 0)], \JSON_THROW_ON_ERROR);

        self::assertSame(2, new PeaksReader(maxLength: 2)->read($document(2))->length());

        $this->expectException(LimitExceeded::class);

        new PeaksReader(maxLength: 2)->read($document(3));
    }

    public function testThePeaksAreAnArrayForTheOneWhoReadsThem(): void
    {
        self::assertSame(['version' => 2, 'channels' => 1, 'sample_rate' => 1, 'samples_per_pixel' => 1, 'bits' => 8, 'length' => 1, 'data' => [0, 1]], new Peaks(1, 1, 8, [0, 1])->toArray());
    }

    public function testTheDurationOfOneRunAtOneHertzIsOneThousandMilliseconds(): void
    {
        self::assertSame(1_000, new Peaks(1, 1, 8, [0, 0])->duration());
    }

    public function testAnImpossibleRunLengthIsRefusedBeforeAnySampleIsRead(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, "\x01\x02\x03\x04");
        rewind($stream);

        try {
            new PeaksComputer()->fromStream($stream, 8_000, 0);
            self::fail('A run holds one sample or more.');
        } catch (InvalidAudio) {
            self::assertSame(0, ftell($stream));
        }
    }

    public function testAnImpossibleDepthInBitsIsRefusedBeforeAnySampleIsRead(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, "\x01\x02\x03\x04");
        rewind($stream);

        try {
            new PeaksComputer()->fromStream($stream, 8_000, 1, 7);
            self::fail('Peaks are on 8 or 16 bits.');
        } catch (InvalidAudio) {
            self::assertSame(0, ftell($stream));
        }
    }

    public function testASampleIsReadWhateverTheSizeOfTheReads(): void
    {
        $samples = pack('v*', 0, 32_767, 65_535, 32_768);

        foreach ([1, 2, 3, 5] as $size) {
            $stream = TrickleStream::open($samples, $size);
            self::assertIsResource($stream);
            self::assertSame([0, 127, -128, -1], new PeaksComputer()->fromStream($stream, 8_000, 2)->data, \sprintf('Reads of %d bytes.', $size));
        }
    }

    public function testAStreamWithNothingToGiveYetEndsTheWaveform(): void
    {
        $stream = StallingStream::open();
        self::assertIsResource($stream);
        self::assertSame([], new PeaksComputer()->fromStream($stream, 8_000, 2)->data);
    }
}
