<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit;

use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Peaks;
use Phil\Audio\PeaksComputer;
use Phil\Audio\PeaksReader;
use Phil\Audio\Tests\Support\TrickleStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Peaks::class)]
#[CoversClass(PeaksReader::class)]
#[CoversClass(PeaksComputer::class)]
final class PeaksTest extends TestCase
{
    public function testPeaksAreWrittenAsTheDocumentedJson(): void
    {
        $peaks = new Peaks(8_000, 800, 8, [-10, 12, -128, 127, 0, 0]);

        self::assertSame(3, $peaks->length());
        self::assertSame(300, $peaks->duration());
        self::assertSame('{"version":2,"channels":1,"sample_rate":8000,"samples_per_pixel":800,"bits":8,"length":3,"data":[-10,12,-128,127,0,0]}', $peaks->toJson());
        self::assertEquals($peaks, new PeaksReader()->read($peaks->toJson()));
    }

    public function testTheAmplitudesAreTheLoudestRunOfEachBar(): void
    {
        $peaks = new Peaks(8_000, 800, 8, [-10, 12, -128, 127, 0, 0, -64, 3]);

        self::assertSame([0.094, 1.0, 0.0, 0.5], $peaks->amplitudes(4));
        self::assertSame([0.094, 1.0, 0.0, 0.5], $peaks->amplitudes(10));
        self::assertSame([1.0, 0.5], $peaks->amplitudes(2));
        self::assertSame([1.0], $peaks->amplitudes(1));
        self::assertSame([0.375, 0.5], new Peaks(1, 1, 16, [-12_288, 0, 0, 16_384])->amplitudes(3));
        self::assertSame([], new Peaks(1, 1, 8, [])->amplitudes(3));
        self::assertSame(0, new Peaks(1, 1, 8, [])->duration());
    }

    public function testAThreeBarWaveformSplitsSevenRunsAtTheirBoundaries(): void
    {
        $peaks = new Peaks(8_000, 1, 8, [0, 10, 0, 20, 0, 30, 0, 40, 0, 50, 0, 60, 0, 70]);

        // 7 runs in 3 bars: runs 0-1, 2-3 and 4-6.
        self::assertSame([0.156, 0.313, 0.547], $peaks->amplitudes(3));
    }

    public function testAWaveformIsOneBarWideOrMore(): void
    {
        $this->expectException(InvalidAudio::class);

        new Peaks(1, 1, 8, [])->amplitudes(0);
    }

    /**
     * @return iterable<string, array{int, int, int, list<int>, string}>
     */
    public static function badPeaks(): iterable
    {
        yield 'no sample rate' => [0, 1, 8, [], 'A sample rate is from 1 to 384000 Hz, not 0.'];
        yield 'rate too high' => [384_001, 1, 8, [], 'A sample rate is from 1 to 384000 Hz, not 384001.'];
        yield 'empty run' => [1, 0, 8, [], 'A run holds one sample or more.'];
        yield '24 bits' => [1, 1, 24, [], 'Peaks are on 8 or 16 bits, not 24.'];
        yield 'odd data' => [1, 1, 8, [0], 'The data of peaks is pairs: the lowest and the highest sample of each run.'];
        yield 'below 8 bits' => [1, 1, 8, [0, 0, -129, 0], 'The run 1 is not a lowest and a highest sample on 8 bits.'];
        yield 'above 8 bits' => [1, 1, 8, [0, 128], 'The run 0 is not a lowest and a highest sample on 8 bits.'];
        yield 'above 16 bits' => [1, 1, 16, [0, 32_768], 'The run 0 is not a lowest and a highest sample on 16 bits.'];
        yield 'low above high' => [1, 1, 8, [5, 4], 'The run 0 is not a lowest and a highest sample on 8 bits.'];
    }

    /**
     * @param list<int> $data
     */
    #[DataProvider('badPeaks')]
    public function testPeaksRefuseWhatNoWaveformHolds(int $sampleRate, int $samplesPerPixel, int $bits, array $data, string $message): void
    {
        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Peaks($sampleRate, $samplesPerPixel, $bits, $data);
    }

    public function testTheLimitsOfEachRangeAreAccepted(): void
    {
        self::assertSame(384_000, new Peaks(384_000, 1, 16, [-32_768, 32_767, 3, 3])->sampleRate);
    }

    public function testAVersionOneDocumentIsRead(): void
    {
        $peaks = new PeaksReader()->read('{"sample_rate":44100,"samples_per_pixel":512,"bits":16,"length":1,"data":[-32768,32767]}');

        self::assertSame([44_100, 512, 16, [-32_768, 32_767]], [$peaks->sampleRate, $peaks->samplesPerPixel, $peaks->bits, $peaks->data]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badDocuments(): iterable
    {
        $valid = ['version' => 2, 'channels' => 1, 'sample_rate' => 8000, 'samples_per_pixel' => 800, 'bits' => 8, 'length' => 1, 'data' => [0, 1]];

        yield 'not JSON' => ['{', 'The peaks are no JSON: Syntax error'];
        yield 'too deep' => ['{"data":[[[1]]]}', 'The peaks are no JSON: Maximum stack depth exceeded'];
        yield 'a number' => ['3', 'The peaks are a JSON object.'];
        yield 'version 3' => [json_encode(['version' => 3] + $valid, \JSON_THROW_ON_ERROR), 'The peaks are of version 1 or 2.'];
        yield 'two channels' => [json_encode(['channels' => 2] + $valid, \JSON_THROW_ON_ERROR), 'The peaks are of one channel.'];
        yield 'no data' => ['{"version":2}', 'The data of the peaks is a list.'];
        yield 'data an object' => ['{"data":{"a":1}}', 'The data of the peaks is a list.'];
        yield 'float data' => [json_encode(['data' => [0, 1.5]] + $valid, \JSON_THROW_ON_ERROR), 'The data of the peaks is integers.'];
        yield 'wrong length' => [json_encode(['length' => 2] + $valid, \JSON_THROW_ON_ERROR), 'The length of the peaks is the number of their runs.'];
        yield 'no length' => [json_encode(array_diff_key($valid, ['length' => 1]), \JSON_THROW_ON_ERROR), 'The length of the peaks is the number of their runs.'];
        yield 'rate as text' => [json_encode(['sample_rate' => '8000'] + $valid, \JSON_THROW_ON_ERROR), 'The peaks give "sample_rate" as an integer.'];
        yield 'no bits' => [json_encode(array_diff_key($valid, ['bits' => 1]), \JSON_THROW_ON_ERROR), 'The peaks give "bits" as an integer.'];
    }

    #[DataProvider('badDocuments')]
    public function testAReaderRefusesWhatIsNoPeaks(string $json, string $message): void
    {
        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PeaksReader()->read($json);
    }

    public function testAReaderStopsAtItsLimits(): void
    {
        $json = new Peaks(1, 1, 8, [0, 1, 0, 1])->toJson();
        self::assertSame(2, new PeaksReader(\strlen($json), 2)->read($json)->length());

        try {
            new PeaksReader(\strlen($json) - 1)->read($json);
            self::fail('A document past its bytes is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame(\sprintf('The peaks hold more than %d bytes.', \strlen($json) - 1), $limitExceeded->getMessage());
        }

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The peaks hold more than 1 runs.');
        new PeaksReader(maxLength: 1)->read($json);
    }

    public function testRawSamplesBecomePeaksRunByRun(): void
    {
        $samples = pack('v*', 0, 256, 65_535 - 511, 1_000, 32_767, 32_768);
        $peaks = new PeaksComputer()->fromString($samples, 8_000, 2);

        self::assertSame([0, 1, -2, 3, -128, 127], $peaks->data);
        self::assertSame([8_000, 2, 8], [$peaks->sampleRate, $peaks->samplesPerPixel, $peaks->bits]);

        $sixteen = new PeaksComputer()->fromString($samples, 8_000, 4, 16);
        self::assertSame([-512, 1_000, -32_768, 32_767], $sixteen->data);
    }

    public function testAnOddByteAndAPartialRunAreKept(): void
    {
        $peaks = new PeaksComputer()->fromString(pack('v*', 512, 1_024, 2_048)."\x01", 8_000, 2);

        self::assertSame([2, 4, 8, 8], $peaks->data);
        self::assertSame([], new PeaksComputer()->fromString('', 8_000, 2)->data);
        self::assertSame([], new PeaksComputer()->fromString("\x01", 8_000, 2)->data);
    }

    public function testSamplesAcrossChunksAreReadInOrder(): void
    {
        // 40 000 samples, more than one chunk of 65 536 bytes.
        $samples = str_repeat(pack('v', 256), 32_767).pack('v', 32_767).str_repeat(pack('v', 512), 7_232);

        self::assertSame([1, 1, 1, 127], new PeaksComputer()->fromString($samples, 8_000, 20_000)->data);
    }

    public function testASampleSplitBetweenTwoReadsIsJoined(): void
    {
        $stream = TrickleStream::open(pack('v*', 256, 512, 65_535 - 255, 1_024, 2_048)."\x01");
        self::assertIsResource($stream);
        $peaks = new PeaksComputer()->fromStream($stream, 8_000, 2);
        fclose($stream);

        self::assertSame([1, 2, -1, 4, 8, 8], $peaks->data);
    }

    public function testAComputerStopsAtItsLimitAndRefusesBadSettings(): void
    {
        self::assertSame(2, new PeaksComputer(2)->fromString(pack('v*', 1, 2), 8_000, 1, 16)->length());

        try {
            new PeaksComputer(2)->fromString(pack('v*', 1, 2, 3), 8_000, 1);
            self::fail('A waveform past its runs is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The waveform would hold more than 2 runs.', $limitExceeded->getMessage());
        }

        try {
            new PeaksComputer()->fromString('', 8_000, 0);
            self::fail('An empty run is refused.');
        } catch (InvalidAudio $invalidAudio) {
            self::assertSame('A run holds one sample or more.', $invalidAudio->getMessage());
        }

        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains('Peaks are on 8 or 16 bits, not 12.');
        new PeaksComputer()->fromString('', 8_000, 1, 12);
    }
}
