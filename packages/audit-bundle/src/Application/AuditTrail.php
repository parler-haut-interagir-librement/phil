<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use Phil\AuditBundle\Application\Contract\AuditNotifier;
use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;
use Phil\AuditBundle\Domain\Exception\ChainConflict;
use Psr\Clock\ClockInterface;

/**
 * Writes the trail: each event serialized by the application's frozen `CanonicalSerializer` — the non-personal
 * fields and the commitments of the personal ones, never their plaintext —, indexed by its facts, stamped with the
 * current tenant when a `TenantScope` gives one, and chained to the head of its segment. With site segments, the row of
 * a tenant goes to that tenant's own segment ({@see SiteSegment}), so that every site has a chain of its own. A row
 * another process appended first is retried on the new head, a few times. Once recorded, a row is passed to the
 * `AuditNotifier`, when there is one.
 */
final readonly class AuditTrail
{
    private const int ATTEMPTS = 3;

    public function __construct(
        private AuditRowRepository $rows,
        private CanonicalSerializer $serializer,
        private ClockInterface $clock,
        private ?TenantScope $tenants = null,
        private ?AuditNotifier $notifier = null,
        private bool $siteSegments = false,
    ) {
    }

    /**
     * @param array<string, bool|float|int|string|null> $fields the payload, serialized as is
     * @param Facts|null                                $facts  who did what to which record; without a tenant of its
     *                                                          own, the current one
     */
    public function record(string $segment, array $fields, ?Facts $facts = null): AuditRow
    {
        $facts ??= Facts::none();

        if (null === $facts->tenant) {
            $facts = $facts->inTenant($this->tenants?->current());
        }

        return $this->append($this->segmentFor($segment, $facts->tenant), RowKind::Event, $this->serializer->serialize($fields), $facts);
    }

    /**
     * Records a restore of the database, to anchor at once: the rows after the last anchor may be gone, and this row
     * explains why rather than leaving the gap an alarm. With site segments, a restore of one site is recorded in that
     * site's segment, its tenant named.
     *
     * @param string|null $tenant the site restored; `null` for a segment shared by every site
     */
    public function recordDiscontinuity(string $segment, string $reason, ?string $tenant = null): AuditRow
    {
        return $this->append($this->segmentFor($segment, $tenant), RowKind::Discontinuity, $this->serializer->serialize(['discontinuity' => $reason]), Facts::none()->inTenant($tenant));
    }

    /**
     * The segment a row of that tenant is recorded in: the site's own with site segments, the one named otherwise.
     */
    public function segmentFor(string $segment, ?string $tenant): string
    {
        return $this->siteSegments && null !== $tenant ? SiteSegment::of($segment, $tenant) : $segment;
    }

    private function append(string $segment, RowKind $kind, string $payload, Facts $facts): AuditRow
    {
        $row = null;

        for ($attempt = 1; $attempt < self::ATTEMPTS && !$row instanceof AuditRow; ++$attempt) {
            try {
                $row = $this->appendOnHead($segment, $kind, $payload, $facts);
            } catch (ChainConflict) {
                // Another process appended first: read the new head and try again.
            }
        }

        $row ??= $this->appendOnHead($segment, $kind, $payload, $facts);
        $this->notifier?->recorded($row);

        return $row;
    }

    private function appendOnHead(string $segment, RowKind $kind, string $payload, Facts $facts): AuditRow
    {
        $row = AuditRow::append($this->rows->head($segment), $segment, $kind, $this->clock->now(), $this->serializer->version(), $payload, $facts);
        $this->rows->append($row);

        return $row;
    }
}
