<?php

declare(strict_types=1);

namespace Phil\Shifts\Format;

use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

/**
 * A shift as the fields of a document, and back. The fields are those of `Shift`, in snake case, the instants in
 * RFC 3339; a field at its default may be left out.
 */
final class ShiftFormat
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Shift $shift): array
    {
        return array_filter([
            'id' => $shift->id,
            'title' => $shift->title,
            'kind' => $shift->kind->value,
            'starts_at' => Fields::write($shift->startsAt),
            'ends_at' => Fields::write($shift->endsAt),
            'minutes' => $shift->minutes,
            'capacity' => $shift->capacity,
            'mode' => $shift->mode->value,
            'reserve' => $shift->reserve,
            'required_role' => $shift->requiredRole,
            'critical' => $shift->critical,
            'weight' => $shift->weight,
            'swap_approval' => $shift->swapApproval,
            'location' => $shift->location,
            'description' => $shift->description,
        ], static fn (mixed $value): bool => null !== $value && false !== $value && '' !== $value);
    }

    /**
     * @throws InvalidDocument when a field is missing, of the wrong shape or outside its bounds
     */
    public static function fromArray(mixed $data, string $path = '$'): Shift
    {
        $fields = Fields::of($data, $path);
        $kind = ShiftKind::tryFrom($fields->string('kind')) ?? throw InvalidDocument::at($fields->path('kind'), 'must be fixed, task or duration');
        $mode = AssignmentMode::tryFrom($fields->has('mode') ? $fields->string('mode') : AssignmentMode::SelfSignUp->value)
            ?? throw InvalidDocument::at($fields->path('mode'), 'must be self_sign_up, assigned or interest');

        try {
            return new Shift(
                $fields->string('id'),
                $fields->string('title'),
                $kind,
                $fields->instant('starts_at'),
                $fields->instant('ends_at'),
                $fields->nullableInt('minutes'),
                $fields->int('capacity', 1),
                $mode,
                $fields->int('reserve', 0),
                $fields->nullableString('required_role'),
                $fields->bool('critical'),
                $fields->int('weight', 100),
                $fields->bool('swap_approval'),
                $fields->optionalString('location'),
                $fields->optionalString('description'),
            );
        } catch (InvalidShift $invalidShift) {
            throw InvalidDocument::at($path, $invalidShift->getMessage(), $invalidShift);
        }
    }
}
