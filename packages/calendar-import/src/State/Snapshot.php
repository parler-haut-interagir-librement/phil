<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\CalendarImport\EventVersion;
use Phil\CalendarImport\Exception\InvalidSource;

/**
 * What one successful read of a source held: the version of each event, by its key, and when it was read.
 */
final readonly class Snapshot
{
    /**
     * @param array<int|string, EventVersion> $versions by key — a key of digits only is an integer in PHP —, in the order of the source
     */
    public function __construct(
        public DateTimeImmutable $takenAt,
        public array $versions,
    ) {
    }

    /**
     * What changed from an earlier snapshot to this one; from no snapshot, every event is added.
     */
    public function compare(?self $previous): SnapshotDiff
    {
        $before = $previous->versions ?? [];
        $added = [];
        $changed = [];
        $reasons = [];
        $fields = [];

        foreach ($this->versions as $key => $version) {
            $old = $before[$key] ?? null;

            if (!$old instanceof EventVersion) {
                $added[] = (string) $key;

                continue;
            }

            $reason = $version->changedFrom($old);

            if ($reason instanceof ChangeReason) {
                $changed[] = (string) $key;
                $reasons[$key] = $reason;
                $fields[$key] = $version->changedFields($old);
            }
        }

        $removed = array_map(strval(...), array_keys(array_diff_key($before, $this->versions)));

        return new SnapshotDiff($added, $changed, $removed, $reasons, $fields);
    }

    /**
     * @return array{takenAt: string, versions: array<int|string, array{sequence: int, lastModified: ?string, fingerprint: string, fields: array<string, string>}>}
     */
    public function toArray(): array
    {
        return [
            'takenAt' => $this->takenAt->format(DateTimeInterface::ATOM),
            'versions' => array_map(static fn (EventVersion $version): array => $version->toArray(), $this->versions),
        ];
    }

    /**
     * A snapshot as `toArray()` wrote it.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidSource
     */
    public static function fromArray(array $data): self
    {
        $takenAt = $data['takenAt'] ?? null;
        $takenAt = \is_string($takenAt) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $takenAt) : null;

        $versions = $data['versions'] ?? null;

        if (!$takenAt instanceof DateTimeImmutable || !\is_array($versions)) {
            throw InvalidSource::because('A stored snapshot holds the moment it was taken, written as ATOM, and its versions.');
        }

        $read = [];

        foreach ($versions as $key => $version) {
            if (!\is_array($version)) {
                throw InvalidSource::because('The versions of a stored snapshot are versions by key.');
            }

            $read[(string) $key] = EventVersion::fromArray($version);
        }

        return new self($takenAt, $read);
    }
}
