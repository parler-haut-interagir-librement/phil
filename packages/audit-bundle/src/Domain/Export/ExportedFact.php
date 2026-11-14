<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Export;

use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * What a dashboard receives of a row: its place, its instant, its kind, the facts it is indexed by and its hash —
 * identifiers only. The payload never leaves the trail: a dashboard counts and charts who did what to which record,
 * and a person who needs the fields reads the row where it is, through `AuditLog::row()`.
 *
 * `$unixNanos` is the instant in nanoseconds since the Unix epoch, as a decimal string, which log protocols carry.
 *
 * The segment and the sequence identify the row: a feed sends each row at least once, and a receiver that keeps the
 * pair drops a row sent again.
 */
final readonly class ExportedFact
{
    private function __construct(
        public string $segment,
        public int $sequence,
        public string $instant,
        public string $kind,
        public ?string $action,
        public ?string $actor,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $tenant,
        public string $hash,
        public string $unixNanos,
    ) {
    }

    public static function of(AuditRow $row): self
    {
        $facts = $row->facts;

        return new self($row->segment, $row->sequence, $row->instant(), $row->kind->value, $facts->action, $facts->actor, $facts->subject?->type, $facts->subject?->id, $facts->tenant, $row->hash, $row->recordedAt->format('Uu').'000');
    }

    /**
     * The fact as a flat map, every key present, `null` for a fact the row does not have.
     *
     * @return array{segment: string, sequence: int, recorded_at: string, kind: string, action: ?string, actor: ?string, subject_type: ?string, subject_id: ?string, tenant: ?string, hash: string}
     */
    public function toArray(): array
    {
        return [
            'segment' => $this->segment,
            'sequence' => $this->sequence,
            'recorded_at' => $this->instant,
            'kind' => $this->kind,
            'action' => $this->action,
            'actor' => $this->actor,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'tenant' => $this->tenant,
            'hash' => $this->hash,
        ];
    }
}
