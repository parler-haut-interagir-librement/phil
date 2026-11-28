<?php

declare(strict_types=1);

namespace Phil\Shifts\Format;

use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;

/**
 * The places of a roster as a list of fields, and back with their shift: what a storage keeps beside the shift.
 */
final class RosterFormat
{
    private const int MAX_PLACES = Shift::MAX_CAPACITY + Shift::MAX_RESERVE + Roster::MAX_INTERESTED;

    private function __construct()
    {
    }

    /**
     * @return list<array<string, string>>
     */
    public static function toArray(Roster $roster): array
    {
        return array_map(static fn (Place $place): array => array_filter([
            'volunteer' => $place->volunteer,
            'status' => $place->status->value,
            'at' => Fields::write($place->at),
            'assigned_by' => $place->assignedBy,
        ], static fn (?string $value): bool => null !== $value), $roster->places);
    }

    /**
     * @throws InvalidDocument when a place is of the wrong shape, or the places do not fit the shift
     */
    public static function fromArray(Shift $shift, mixed $data, string $path = '$'): Roster
    {
        $places = Fields::of(['places' => $data], $path)->list('places', self::MAX_PLACES);
        $read = [];

        foreach ($places as $index => $place) {
            $fields = Fields::of($place, $path.'['.$index.']');
            $status = PlaceStatus::tryFrom($fields->string('status')) ?? throw InvalidDocument::at($fields->path('status'), 'must be confirmed, reserve or interested');

            try {
                $read[] = new Place($fields->string('volunteer'), $status, $fields->instant('at'), $fields->nullableString('assigned_by'));
            } catch (InvalidShift $invalidShift) {
                throw InvalidDocument::at($path.'['.$index.']', $invalidShift->getMessage(), $invalidShift);
            }
        }

        try {
            return new Roster($shift, $read);
        } catch (InvalidShift $invalidShift) {
            throw InvalidDocument::at($path, $invalidShift->getMessage(), $invalidShift);
        }
    }
}
