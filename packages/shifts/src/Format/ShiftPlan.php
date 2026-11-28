<?php

declare(strict_types=1);

namespace Phil\Shifts\Format;

use JsonException;
use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Shift\Shift;

/**
 * The shifts of an event in the JSON format `phil-shifts/1` — what a coordinator imports, or duplicates from one edition
 * to the next:
 *
 * ```json
 * {"format": "phil-shifts/1", "shifts": [{"id": "desk-sat-am", "title": "Info desk", "kind": "fixed",
 *   "starts_at": "2026-11-28T09:00:00-05:00", "ends_at": "2026-11-28T12:00:00-05:00", "capacity": 2}]}
 * ```
 *
 * A document is read within 1 MiB, a depth of 16 and 2,000 shifts, each identifier once.
 */
final readonly class ShiftPlan
{
    public const string FORMAT = 'phil-shifts/1';

    public const int MAX_BYTES = 1_048_576;

    public const int MAX_SHIFTS = 2000;

    /**
     * @param list<Shift> $shifts
     */
    public function __construct(
        public array $shifts,
    ) {
    }

    /**
     * @throws InvalidDocument when the text is not a document of the format, or a shift is not one
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidDocument::because('A plan of shifts is 1 MiB at most.');
        }

        try {
            $data = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidDocument::because('A plan of shifts is a JSON document.', $jsonException);
        }

        $fields = Fields::of($data, '$');

        if (self::FORMAT !== $fields->string('format')) {
            throw InvalidDocument::at('$.format', 'must be '.self::FORMAT);
        }

        $shifts = [];
        $seen = [];

        foreach ($fields->list('shifts', self::MAX_SHIFTS) as $index => $entry) {
            $shift = ShiftFormat::fromArray($entry, '$.shifts['.$index.']');

            if (isset($seen[$shift->id])) {
                throw InvalidDocument::at('$.shifts['.$index.'].id', 'names a shift already in the plan');
            }

            $seen[$shift->id] = $index;
            $shifts[] = $shift;
        }

        return new self($shifts);
    }

    public function toJson(): string
    {
        return json_encode(['format' => self::FORMAT, 'shifts' => array_map(ShiftFormat::toArray(...), $this->shifts)], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
