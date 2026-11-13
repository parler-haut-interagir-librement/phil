<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\IncidentUpdate;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;

/**
 * Reads incidents and maintenances from plain data — the configuration, or a decoded JSON file:
 *
 * ```yaml
 * incidents:
 *     - id: database-slow
 *       title: Slow pages
 *       impact: degraded
 *       components: [website]
 *       updates:
 *           - { at: '2026-11-12T10:00:00+00:00', state: investigating, message: 'Pages load slowly.' }
 * maintenances:
 *     - { id: upgrade, title: Upgrade, components: [api], starts_at: '2026-11-20T02:00:00Z', ends_at: '2026-11-20T03:00:00Z' }
 * ```
 *
 * Dates are ISO 8601 with their offset. Anything else than these keys and types is refused with InvalidStatusRecord,
 * which names the record and the field, never the value. A list holds 500 records at most, each id once.
 */
final readonly class RecordsReader
{
    public const int MAX_RECORDS = 500;

    /**
     * @param array<mixed> $data
     */
    public function read(array $data): Records
    {
        $this->keys($data, ['incidents', 'maintenances'], 'records');

        return new Records(
            $this->incidents($this->records($data, 'incidents')),
            $this->maintenances($this->records($data, 'maintenances')),
        );
    }

    /**
     * @param list<array<mixed>> $records
     *
     * @return list<Incident>
     */
    private function incidents(array $records): array
    {
        $incidents = [];

        foreach ($records as $index => $record) {
            $where = \sprintf('incident %d', $index + 1);
            $this->keys($record, ['id', 'title', 'impact', 'components', 'updates'], $where);
            $updates = [];

            foreach ($this->list($record, 'updates', $where, Incident::MAX_UPDATES) as $number => $update) {
                if (!\is_array($update)) {
                    throw InvalidStatusRecord::because(\sprintf('Update %d of %s is a map.', $number + 1, $where));
                }

                $at = \sprintf('update %d of %s', $number + 1, $where);
                $this->keys($update, ['at', 'state', 'message'], $at);
                $updates[] = new IncidentUpdate(
                    $this->date($update, 'at', $at),
                    IncidentState::tryFrom($this->string($update, 'state', $at)) ?? throw InvalidStatusRecord::because(\sprintf('The state of %s is "investigating", "identified", "monitoring" or "resolved".', $at)),
                    $this->string($update, 'message', $at),
                );
            }

            $incidents[] = new Incident(
                $this->string($record, 'id', $where),
                $this->string($record, 'title', $where),
                ComponentStatus::tryFrom($this->string($record, 'impact', $where)) ?? throw InvalidStatusRecord::because(\sprintf('The impact of %s is "degraded", "partial_outage" or "major_outage".', $where)),
                $this->strings($record, 'components', $where),
                $updates,
            );
        }

        $this->unique(array_map(static fn (Incident $incident): string => $incident->id, $incidents), 'incident');

        return $incidents;
    }

    /**
     * @param list<array<mixed>> $records
     *
     * @return list<ScheduledMaintenance>
     */
    private function maintenances(array $records): array
    {
        $maintenances = [];

        foreach ($records as $index => $record) {
            $where = \sprintf('maintenance %d', $index + 1);
            $this->keys($record, ['id', 'title', 'description', 'components', 'starts_at', 'ends_at'], $where);
            $description = $record['description'] ?? null;

            if (null !== $description && !\is_string($description)) {
                throw InvalidStatusRecord::because(\sprintf('The description of %s is a string.', $where));
            }

            $maintenances[] = new ScheduledMaintenance(
                $this->string($record, 'id', $where),
                $this->string($record, 'title', $where),
                $this->strings($record, 'components', $where),
                $this->date($record, 'starts_at', $where),
                $this->date($record, 'ends_at', $where),
                $description,
            );
        }

        $this->unique(array_map(static fn (ScheduledMaintenance $maintenance): string => $maintenance->id, $maintenances), 'maintenance');

        return $maintenances;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private function records(array $data, string $key): array
    {
        $records = [];

        foreach ($this->list($data, $key, $key, self::MAX_RECORDS) as $index => $record) {
            if (!\is_array($record)) {
                throw InvalidStatusRecord::because(\sprintf('Each of the %s is a map; %d is not.', $key, $index + 1));
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    private function list(array $data, string $key, string $where, int $max): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidStatusRecord::because(\sprintf('The %s of %s are a list.', $key, $where));
        }

        if (\count($value) > $max) {
            throw InvalidStatusRecord::because(\sprintf('The %s of %s are %d at most.', $key, $where, $max));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $allowed
     */
    private function keys(array $data, array $allowed, string $where): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $allowed, true)) {
                throw InvalidStatusRecord::because(\sprintf('The keys of %s are %s.', $where, implode(', ', $allowed)));
            }
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidStatusRecord::because(\sprintf('The %s of %s is a string.', $key, $where));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function strings(array $data, string $key, string $where): array
    {
        $strings = [];

        foreach ($this->list($data, $key, $where, self::MAX_RECORDS) as $value) {
            if (!\is_string($value)) {
                throw InvalidStatusRecord::because(\sprintf('The %s of %s are strings.', $key, $where));
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * An ISO 8601 date and time with its offset, such as `2026-11-12T10:00:00+00:00` or `2026-11-12T10:00:00Z`; a
     * date the calendar does not have is refused.
     *
     * @param array<mixed> $data
     */
    private function date(array $data, string $key, string $where): DateTimeImmutable
    {
        $text = $this->string($data, $key, $where);
        $date = 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $text)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $text)
            : false;

        if (false === $date || $date->format('Y-m-d\TH:i:s') !== substr($text, 0, 19)) {
            throw InvalidStatusRecord::because(\sprintf('The %s of %s is a date and time with its offset, such as 2026-11-12T10:00:00+00:00.', $key, $where));
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @param list<string> $ids
     */
    private function unique(array $ids, string $kind): void
    {
        if (\count($ids) !== \count(array_unique($ids))) {
            throw InvalidStatusRecord::because(\sprintf('Each %s id is given once.', $kind));
        }
    }
}
