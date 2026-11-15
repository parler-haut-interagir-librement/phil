<?php

declare(strict_types=1);

namespace Phil\Audio;

use JsonException;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;

/**
 * Reads the JSON of peaks — version 1 or 2, one channel —, as `Peaks::toJson()` and audiowaveform write it. A document
 * past the bytes or the runs it is given is refused before it is decoded or built.
 */
final readonly class PeaksReader
{
    /**
     * @param int $maxBytes  the bytes of a document
     * @param int $maxLength the runs of a waveform
     */
    public function __construct(
        private int $maxBytes = 8_388_608,
        private int $maxLength = 1_000_000,
    ) {
    }

    /**
     * @throws InvalidAudio  when the document is no peaks of one channel
     * @throws LimitExceeded when it is past the limits
     */
    public function read(string $json): Peaks
    {
        if (\strlen($json) > $this->maxBytes) {
            throw new LimitExceeded(\sprintf('The peaks hold more than %d bytes.', $this->maxBytes));
        }

        try {
            $document = json_decode($json, true, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidAudio('The peaks are no JSON: '.$jsonException->getMessage(), 0, $jsonException);
        }

        if (!\is_array($document)) {
            throw new InvalidAudio('The peaks are a JSON object.');
        }

        $version = $document['version'] ?? 1;

        if (1 !== $version && 2 !== $version) {
            throw new InvalidAudio('The peaks are of version 1 or 2.');
        }

        if (1 !== ($document['channels'] ?? 1)) {
            throw new InvalidAudio('The peaks are of one channel.');
        }

        $data = $document['data'] ?? null;

        if (!\is_array($data) || !array_is_list($data)) {
            throw new InvalidAudio('The data of the peaks is a list.');
        }

        if (\count($data) > 2 * $this->maxLength) {
            throw new LimitExceeded(\sprintf('The peaks hold more than %d runs.', $this->maxLength));
        }

        $values = [];

        foreach ($data as $value) {
            if (!\is_int($value)) {
                throw new InvalidAudio('The data of the peaks is integers.');
            }

            $values[] = $value;
        }

        if (($document['length'] ?? null) !== intdiv(\count($values), 2)) {
            throw new InvalidAudio('The length of the peaks is the number of their runs.');
        }

        return new Peaks($this->integer($document, 'sample_rate'), $this->integer($document, 'samples_per_pixel'), $this->integer($document, 'bits'), $values);
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private function integer(array $document, string $key): int
    {
        $value = $document[$key] ?? null;

        if (!\is_int($value)) {
            throw new InvalidAudio(\sprintf('The peaks give "%s" as an integer.', $key));
        }

        return $value;
    }
}
