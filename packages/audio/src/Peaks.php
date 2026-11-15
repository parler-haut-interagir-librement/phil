<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;

/**
 * The waveform of an audio file, computed once on the server: for each run of `samplesPerPixel` samples of one channel,
 * the lowest and the highest sample, on 8 or 16 bits. `toArray()` writes it as the JSON documented in
 * `doc/waveform.md`, the shape the BBC's audiowaveform tool gives, so that a waveform it computed is read as well.
 */
final readonly class Peaks
{
    public const int VERSION = 2;

    /**
     * @param list<int> $data the lowest and the highest sample of each run, in turn
     *
     * @throws InvalidAudio when a value is outside its range, the data is not pairs, or a pair's lowest is above its
     *                      highest
     */
    public function __construct(
        public int $sampleRate,
        public int $samplesPerPixel,
        public int $bits,
        public array $data,
    ) {
        if ($sampleRate < 1 || $sampleRate > 384_000) {
            throw new InvalidAudio(\sprintf('A sample rate is from 1 to 384000 Hz, not %d.', $sampleRate));
        }

        if ($samplesPerPixel < 1) {
            throw new InvalidAudio('A run holds one sample or more.');
        }

        if (8 !== $bits && 16 !== $bits) {
            throw new InvalidAudio(\sprintf('Peaks are on 8 or 16 bits, not %d.', $bits));
        }

        if (1 === \count($data) % 2) {
            throw new InvalidAudio('The data of peaks is pairs: the lowest and the highest sample of each run.');
        }

        $limit = 1 << ($bits - 1);

        for ($index = 0, $count = \count($data); $index < $count; $index += 2) {
            [$low, $high] = [$data[$index], $data[$index + 1]];

            if ($low < -$limit || $high >= $limit || $low > $high) {
                throw new InvalidAudio(\sprintf('The run %d is not a lowest and a highest sample on %d bits.', intdiv($index, 2), $bits));
            }
        }
    }

    /**
     * The number of runs.
     */
    public function length(): int
    {
        return intdiv(\count($this->data), 2);
    }

    /**
     * The duration the peaks cover, in milliseconds.
     */
    public function duration(): int
    {
        return intdiv($this->length() * $this->samplesPerPixel * 1000, $this->sampleRate);
    }

    /**
     * The loudness of the waveform in `$width` bars, each from 0 to 1, rounded to three decimals: the larger of the
     * lowest and the highest sample of the runs it covers, over the largest value of the bits. Fewer bars when there
     * are fewer runs.
     *
     * @return list<float>
     *
     * @throws InvalidAudio when the width is below 1
     */
    public function amplitudes(int $width): array
    {
        if ($width < 1) {
            throw new InvalidAudio('A waveform is one bar wide or more.');
        }

        $length = $this->length();
        $bars = min($width, $length);
        $scale = 1 << ($this->bits - 1);
        $amplitudes = [];

        for ($bar = 0; $bar < $bars; ++$bar) {
            $from = intdiv($bar * $length, $bars);
            $to = intdiv(($bar + 1) * $length, $bars);
            $peak = 0;

            for ($run = $from; $run < $to; ++$run) {
                $peak = max($peak, -$this->data[2 * $run], $this->data[2 * $run + 1]);
            }

            $amplitudes[] = round(min($peak, $scale) / $scale, 3);
        }

        return $amplitudes;
    }

    /**
     * @return array{version: int, channels: int, sample_rate: int, samples_per_pixel: int, bits: int, length: int, data: list<int>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'channels' => 1,
            'sample_rate' => $this->sampleRate,
            'samples_per_pixel' => $this->samplesPerPixel,
            'bits' => $this->bits,
            'length' => $this->length(),
            'data' => $this->data,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR);
    }
}
