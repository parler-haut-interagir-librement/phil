<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Read\SegmentStatus;

/**
 * The state of the hash chain for a viewer: every segment with its head, read on the key, and the verification of one
 * segment against its anchors when asked for, since it reads every row of that segment. In a tenant — when a
 * `TenantScope` gives one —, only that site's own segments ({@see SiteSegment}) are listed and verified: a segment
 * shared by every site, or another site's, is not shown.
 */
final readonly class ChainStatus
{
    public function __construct(
        private AuditRowRepository $rows,
        private TrailVerification $verification,
        private ?TenantScope $tenants = null,
    ) {
    }

    /**
     * @return list<SegmentStatus> every segment that can be shown, by name, not verified
     */
    public function segments(): array
    {
        $statuses = [];

        foreach ($this->rows->segments() as $segment) {
            if ($this->shows($segment)) {
                $statuses[] = new SegmentStatus($segment, $this->rows->head($segment));
            }
        }

        return $statuses;
    }

    /**
     * One segment, verified; a segment without rows holds, with no head, and so does a segment that cannot be shown.
     */
    public function verify(string $segment): SegmentStatus
    {
        if (!$this->shows($segment)) {
            return new SegmentStatus($segment, null, []);
        }

        return new SegmentStatus($segment, $this->rows->head($segment), $this->verification->verify([$segment])[$segment] ?? []);
    }

    private function shows(string $segment): bool
    {
        $tenant = $this->tenants?->current();

        return null === $tenant || SiteSegment::belongsTo($segment, $tenant);
    }
}
