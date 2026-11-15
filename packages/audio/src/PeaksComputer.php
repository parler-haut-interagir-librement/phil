<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;

/**
 * Computes the peaks of raw audio — one channel of signed 16-bit little-endian samples, as a decoder writes it — read
 * in chunks, so that an hour of sound never sits in memory at once.
 */
final readonly class PeaksComputer
{
    private const int CHUNK = 65_536;

    /**
     * @param int $maxLength the runs of a waveform
     */
    public function __construct(
        private int $maxLength = 1_000_000,
    ) {
    }

    /**
     * @param resource $stream the samples, read to its end
     *
     * @throws InvalidAudio  when a value is outside its range
     * @throws LimitExceeded when the waveform would hold more runs than allowed
     */
    public function fromStream(mixed $stream, int $sampleRate, int $samplesPerPixel, int $bits = 8): Peaks
    {
        if ($samplesPerPixel < 1) {
            throw new InvalidAudio('A run holds one sample or more.');
        }

        if (8 !== $bits && 16 !== $bits) {
            throw new InvalidAudio(\sprintf('Peaks are on 8 or 16 bits, not %d.', $bits));
        }

        $shift = 16 - $bits;
        $data = [];
        $low = \PHP_INT_MAX;
        $high = \PHP_INT_MIN;
        $count = 0;
        $carry = '';

        while (!feof($stream)) {
            $chunk = fread($stream, self::CHUNK);

            if (false === $chunk || '' === $chunk) {
                break;
            }

            $chunk = $carry.$chunk;
            $even = \strlen($chunk) & ~1;
            $carry = substr($chunk, $even);

            if (0 === $even) {
                continue;
            }

            /** @var array<int, int> $samples */
            $samples = unpack('v*', substr($chunk, 0, $even));

            foreach ($samples as $sample) {
                $sample = $sample >= 32_768 ? $sample - 65_536 : $sample;
                $low = min($low, $sample);
                $high = max($high, $sample);

                if (++$count === $samplesPerPixel) {
                    $this->push($data, $low >> $shift, $high >> $shift);
                    [$low, $high, $count] = [\PHP_INT_MAX, \PHP_INT_MIN, 0];
                }
            }
        }

        if ($count > 0) {
            $this->push($data, $low >> $shift, $high >> $shift);
        }

        return new Peaks($sampleRate, $samplesPerPixel, $bits, $data);
    }

    /**
     * The peaks of samples held in a string.
     *
     * @throws InvalidAudio  when a value is outside its range
     * @throws LimitExceeded when the waveform would hold more runs than allowed
     */
    public function fromString(string $samples, int $sampleRate, int $samplesPerPixel, int $bits = 8): Peaks
    {
        $stream = fopen('php://memory', 'w+');
        \assert(\is_resource($stream));

        try {
            fwrite($stream, $samples);
            rewind($stream);

            return $this->fromStream($stream, $sampleRate, $samplesPerPixel, $bits);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<int> $data
     */
    private function push(array &$data, int $low, int $high): void
    {
        if (\count($data) >= 2 * $this->maxLength) {
            throw new LimitExceeded(\sprintf('The waveform would hold more than %d runs.', $this->maxLength));
        }

        $data[] = $low;
        $data[] = $high;
    }
}
