<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

/**
 * Verifies a segment: every row's hash recomputed, every link to the row before, no row missing, every row of a site
 * segment of that site's tenant, and every anchor's boundary hashes found in the chain.
 *
 * An anchor whose head no row has any more is a rewrite, an alarm. It is explained by a restore, and reported as an
 * explained break rather than an alarm, only when a discontinuity row was recorded after that anchor's period and
 * the next anchor whose head the chain still has covers that discontinuity: it was anchored, so it was there before
 * that anchor was written. Once the segment has anchors, a discontinuity no anchor covers is an alarm too.
 *
 * The rows are read once, in order; what is kept is the first hash, the sequence at which each anchored head was
 * found, and the discontinuities, so memory grows with the anchors and the restores, not with the rows. The anchors
 * that hold are sorted by period once, and the next one after a lost anchor is found by a binary search: a segment
 * with thousands of lost anchors costs a logarithm per anchor, not a pass over every anchor.
 */
final class ChainVerifier
{
    /**
     * Whether each tenant met in the segment being verified may stand in it, `null` for a segment that is not a site
     * segment: the digest of a tenant is taken once, not once per row.
     *
     * @var array<string, bool>|null
     */
    private ?array $admitted = null;

    /**
     * @param iterable<AuditRow> $rows    by sequence
     * @param list<Anchor>       $anchors
     *
     * @return list<ChainBreak> the alarms and the explained breaks, in the order found
     */
    public function verify(string $segment, iterable $rows, array $anchors): array
    {
        $this->admitted = SiteSegment::isSite($segment) ? [] : null;
        $breaks = [];
        $expected = 1;
        $previous = AuditRow::GENESIS;
        $first = null;
        $discontinuities = [];
        // The sequence of the row that has each anchored head, null until one is read.
        $found = [];

        foreach ($anchors as $anchor) {
            $found[$anchor->headHash] = null;
        }

        foreach ($rows as $row) {
            array_push($breaks, ...$this->rowBreaks($segment, $row, $expected, $previous));

            if (RowKind::Discontinuity === $row->kind) {
                $discontinuities[] = $row;
            }

            if (\array_key_exists($row->hash, $found)) {
                $found[$row->hash] ??= $row->sequence;
            }

            $first ??= $row->hash;
            $previous = $row->hash;
            $expected = $row->sequence + 1;
        }

        array_push($breaks, ...$this->anchorBreaks($segment, $anchors, $first, $found, $discontinuities));

        // Without anchors there is nothing to compare; `$found` holds a key for each anchored head.
        if ([] !== $found) {
            // An anchor's head covers a discontinuity when it is that row or a row after it: the latest head found
            // covers every discontinuity any head covers. `max()` ranks a head not found, `null`, below every sequence,
            // which starts at 1: it is `null` only when no head was found.
            $latest = max($found);

            foreach ($discontinuities as $discontinuity) {
                if (null === $latest || $discontinuity->sequence > $latest) {
                    $breaks[] = new ChainBreak($segment, $discontinuity->sequence, 'the discontinuity is covered by no anchor');
                }
            }
        }

        return $breaks;
    }

    /**
     * What one row breaks: a row missing before it, a field changed, a link cut, or — in a site segment — a tenant
     * other than the site's.
     *
     * @return list<ChainBreak>
     */
    private function rowBreaks(string $segment, AuditRow $row, int $expected, string $previous): array
    {
        $breaks = [];

        if ($row->sequence !== $expected) {
            $breaks[] = new ChainBreak($segment, $row->sequence, \sprintf('row %d is missing', $expected));
        }

        if (!$row->isIntact()) {
            $breaks[] = new ChainBreak($segment, $row->sequence, 'the row was changed after it was written');
        }

        if (!hash_equals($previous, $row->previousHash)) {
            $breaks[] = new ChainBreak($segment, $row->sequence, 'the row does not link to the row before it');
        }

        if (!$this->admits($segment, $row->facts->tenant)) {
            $breaks[] = new ChainBreak($segment, $row->sequence, 'the row is not of the site the segment belongs to');
        }

        return $breaks;
    }

    /**
     * Whether the tenant may stand in the segment, its answer kept for the next rows.
     */
    private function admits(string $segment, ?string $tenant): bool
    {
        if (null === $this->admitted) {
            return true;
        }

        if (null === $tenant) {
            return false;
        }

        return $this->admitted[$tenant] ??= SiteSegment::belongsTo($segment, $tenant);
    }

    /**
     * What the anchors break: an anchor that does not start where the chain starts, or whose head no row has, unless
     * a covered discontinuity explains it.
     *
     * @param list<Anchor>            $anchors
     * @param array<string, int|null> $found
     * @param list<AuditRow>          $discontinuities
     *
     * @return list<ChainBreak>
     */
    private function anchorBreaks(string $segment, array $anchors, ?string $first, array $found, array $discontinuities): array
    {
        $breaks = [];
        $holding = $this->holding($anchors, $found);

        foreach ($anchors as $anchor) {
            if (null === $first || !hash_equals($first, $anchor->firstHash)) {
                $breaks[] = new ChainBreak($segment, null, \sprintf('the anchor of %s does not start where the chain starts', $anchor->periodEnd->format(\DATE_ATOM)));

                continue;
            }

            if (null !== $found[$anchor->headHash]) {
                continue;
            }

            $restore = $this->restoreAfter($anchor, $holding, $discontinuities);
            $breaks[] = $restore instanceof AuditRow
                ? new ChainBreak($segment, $restore->sequence, \sprintf('no row has the head hash anchored at %s: explained by this discontinuity', $anchor->periodEnd->format(\DATE_ATOM)), explained: true)
                : new ChainBreak($segment, null, \sprintf('no row has the head hash anchored at %s', $anchor->periodEnd->format(\DATE_ATOM)));
        }

        return $breaks;
    }

    /**
     * The anchors whose head a row has, each with the sequence of that row, by period; of two anchors of the same
     * period, the one written first comes first.
     *
     * @param list<Anchor>            $anchors
     * @param array<string, int|null> $found
     *
     * @return list<array{Anchor, int}>
     */
    private function holding(array $anchors, array $found): array
    {
        $holding = [];

        foreach ($anchors as $anchor) {
            if (null !== $found[$anchor->headHash]) {
                $holding[] = [$anchor, $found[$anchor->headHash]];
            }
        }

        // `usort()` is stable: anchors of the same period keep the order they were written in.
        usort($holding, static fn (array $a, array $b): int => $a[0]->periodEnd <=> $b[0]->periodEnd);

        return $holding;
    }

    /**
     * The discontinuity that explains a lost head: recorded after the anchor's period and no later than the next
     * anchor that still holds, which covers it. `null` when there is none.
     *
     * @param list<array{Anchor, int}> $holding         the anchors that hold, by period
     * @param list<AuditRow>           $discontinuities
     */
    private function restoreAfter(Anchor $lost, array $holding, array $discontinuities): ?AuditRow
    {
        // The first anchor that holds with a period after the lost anchor's: the lowest index whose period is later.
        $low = 0;
        $high = \count($holding);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($holding[$middle][0]->periodEnd > $lost->periodEnd) {
                $high = $middle;
            } else {
                $low = $middle + 1;
            }
        }

        if (!isset($holding[$low])) {
            return null;
        }

        [$nextAnchor, $coveredUpTo] = $holding[$low];

        foreach ($discontinuities as $discontinuity) {
            if ($discontinuity->recordedAt > $lost->periodEnd && $discontinuity->recordedAt <= $nextAnchor->periodEnd && $discontinuity->sequence <= $coveredUpTo) {
                return $discontinuity;
            }
        }

        return null;
    }
}
