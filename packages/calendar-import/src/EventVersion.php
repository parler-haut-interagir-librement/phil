<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\CalendarImport\Exception\InvalidSource;
use Phil\CalendarImport\State\ChangeReason;

/**
 * The version of an event from one read: its `SEQUENCE`, its `LAST-MODIFIED`, the fingerprint of its content — every
 * field the application imports, `DTSTAMP`, `SEQUENCE` and `LAST-MODIFIED` left out —, and the fingerprint of each
 * field an editor may replace. Nothing else is kept: a snapshot holds versions, not events.
 */
final readonly class EventVersion
{
    /**
     * @param string                $fingerprint the SHA-256 of the content, in hexadecimal
     * @param array<string, string> $fields      the SHA-256 of each `Field`, by its value
     */
    public function __construct(
        public int $sequence,
        public ?DateTimeImmutable $lastModified,
        public string $fingerprint,
        public array $fields = [],
    ) {
    }

    /**
     * Why this version differs from an earlier one, or null when their content is the same: a new `SEQUENCE`, else a
     * new `LAST-MODIFIED`, else the content alone. A `SEQUENCE` or a `LAST-MODIFIED` that moves with the same content
     * changes nothing.
     */
    public function changedFrom(self $previous): ?ChangeReason
    {
        return match (true) {
            $this->fingerprint === $previous->fingerprint => null,
            $this->sequence !== $previous->sequence => ChangeReason::Sequence,
            $this->lastModified instanceof DateTimeImmutable && $previous->lastModified instanceof DateTimeImmutable && $this->lastModified->getTimestamp() !== $previous->lastModified->getTimestamp() => ChangeReason::LastModified,
            default => ChangeReason::Content,
        };
    }

    /**
     * The fields this version changes from an earlier one.
     *
     * @return list<Field>
     */
    public function changedFields(self $previous): array
    {
        return array_values(array_filter(Field::cases(), fn (Field $field): bool => ($this->fields[$field->value] ?? null) !== ($previous->fields[$field->value] ?? null)));
    }

    /**
     * @return array{sequence: int, lastModified: ?string, fingerprint: string, fields: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'lastModified' => $this->lastModified?->format(DateTimeInterface::ATOM),
            'fingerprint' => $this->fingerprint,
            'fields' => $this->fields,
        ];
    }

    /**
     * A version as `toArray()` wrote it.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidSource
     */
    public static function fromArray(array $data): self
    {
        $sequence = $data['sequence'] ?? null;
        $lastModified = $data['lastModified'] ?? null;
        $fingerprint = $data['fingerprint'] ?? null;
        $fields = $data['fields'] ?? [];

        if (!\is_int($sequence) || !\is_string($fingerprint) || !\is_array($fields) || (null !== $lastModified && !\is_string($lastModified))) {
            throw InvalidSource::because('A stored version holds a sequence, a fingerprint, fields and a last change or null.');
        }

        $read = [];

        foreach ($fields as $name => $hash) {
            if (!\is_string($name) || !\is_string($hash)) {
                throw InvalidSource::because('The fields of a stored version are fingerprints by name.');
            }

            $read[$name] = $hash;
        }

        return new self($sequence, null === $lastModified ? null : self::moment($lastModified), $fingerprint, $read);
    }

    private static function moment(string $text): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $text);

        if (!$moment instanceof DateTimeImmutable) {
            throw InvalidSource::because(\sprintf('"%s" is not a moment written as ATOM.', $text));
        }

        return $moment;
    }
}
