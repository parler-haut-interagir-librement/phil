<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\ChainVerifier;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Domain\Contract\AnchorReader;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;

/**
 * Verifies the trail, segment by segment, against its anchors when they can be read back.
 */
final readonly class TrailVerification
{
    public function __construct(
        private AuditRowRepository $rows,
        private ?AnchorReader $anchors = null,
    ) {
    }

    /**
     * @param list<string>|null $segments every segment when `null`
     * @param string|null       $tenant   only the segments of that site ({@see SiteSegment}) among them
     *
     * @return array<string, list<ChainBreak>> the breaks of each segment verified, alarms and explained ones; the
     *                                         segment holds when none is an alarm
     */
    public function verify(?array $segments = null, ?string $tenant = null): array
    {
        $verifier = new ChainVerifier();
        $breaks = [];

        foreach ($segments ?? $this->rows->segments() as $segment) {
            if (null !== $tenant && !SiteSegment::belongsTo($segment, $tenant)) {
                continue;
            }

            $breaks[$segment] = $verifier->verify($segment, $this->rows->rows($segment), $this->anchors?->anchors($segment) ?? []);
        }

        return $breaks;
    }
}
