<?php

declare(strict_types=1);

namespace Phil\Shifts\Format;

use DateTimeImmutable;
use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;

/**
 * An offer of the swap board as the fields of a document, and back.
 */
final class SwapFormat
{
    private function __construct()
    {
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(SwapOffer $offer): array
    {
        return array_filter([
            'id' => $offer->id,
            'shift_id' => $offer->shiftId,
            'offered_by' => $offer->offeredBy,
            'created_at' => Fields::write($offer->createdAt),
            'note' => $offer->note,
            'status' => $offer->status->value,
            'taken_by' => $offer->takenBy,
            'decided_by' => $offer->decidedBy,
            'decided_at' => $offer->decidedAt instanceof DateTimeImmutable ? Fields::write($offer->decidedAt) : null,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }

    /**
     * @throws InvalidDocument when a field is missing, of the wrong shape or outside its bounds
     */
    public static function fromArray(mixed $data, string $path = '$'): SwapOffer
    {
        $fields = Fields::of($data, $path);
        $status = SwapStatus::tryFrom($fields->string('status')) ?? throw InvalidDocument::at($fields->path('status'), 'must be open, pending, completed or cancelled');

        try {
            return new SwapOffer(
                $fields->string('id'),
                $fields->string('shift_id'),
                $fields->string('offered_by'),
                $fields->instant('created_at'),
                $fields->optionalString('note'),
                $status,
                $fields->nullableString('taken_by'),
                $fields->nullableString('decided_by'),
                $fields->nullableInstant('decided_at'),
            );
        } catch (InvalidShift $invalidShift) {
            throw InvalidDocument::at($path, $invalidShift->getMessage(), $invalidShift);
        }
    }
}
