<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Generator;
use Phil\AudienceSegments\Contract\Audience;
use Phil\AudienceSegments\Contract\Exclusions;
use Phil\AudienceSegments\Exception\PortContractViolated;
use Phil\AudienceSegments\Exception\SegmentTooLarge;
use Phil\Rules\Exception\InvalidLimit;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Json\RuleWriter;
use Psr\Clock\ClockInterface;

/**
 * Computes the members of segments over the audience, the global exclusions removed first: one member with the reason,
 * a bounded page of a dynamic segment, or the whole list frozen at a date. Holds no state between calls.
 */
final readonly class Segments
{
    private RuleWriter $writer;

    /** @var int<1, 1000> */
    private int $pageSize;

    private int $maxScanned;

    private int $maxMembers;

    /**
     * @param int $pageSize   the members read from the audience at once
     * @param int $maxScanned the members one call of `page()` reads at most
     * @param int $maxMembers the members a full computation, frozen list or recalculation, reads at most
     */
    public function __construct(
        private Audience $audience,
        private Exclusions $exclusions,
        private ClockInterface $clock,
        int $pageSize = 500,
        int $maxScanned = 10000,
        int $maxMembers = 200000,
    ) {
        if ($pageSize < 1 || $pageSize > 1000) {
            throw InvalidLimit::outOfRange('pageSize', $pageSize, 1, 1000);
        }

        if ($maxScanned < 1 || $maxScanned > 1000000) {
            throw InvalidLimit::outOfRange('maxScanned', $maxScanned, 1, 1000000);
        }

        if ($maxMembers < 1 || $maxMembers > 10000000) {
            throw InvalidLimit::outOfRange('maxMembers', $maxMembers, 1, 10000000);
        }

        $this->pageSize = $pageSize;
        $this->maxScanned = $maxScanned;
        $this->maxMembers = $maxMembers;
        $this->writer = new RuleWriter();
    }

    /**
     * Whether the member belongs to the segment, with the trace of the rule: evaluated even for an excluded member, so
     * that support can see both reasons.
     */
    public function check(Segment $segment, string $member): Membership
    {
        Identifier::check('member', $member);
        $excluded = \in_array($member, $this->exclusions->excluded([$member]), true);

        return new Membership($member, $excluded, $segment->rule->evaluate(Facts::of([Segment::MEMBER => $member])));
    }

    /**
     * Up to `$limit` members of the dynamic segment after the cursor, read from the audience as it is now.
     */
    public function page(Segment $segment, ?string $after = null, int $limit = 100): MemberPage
    {
        if ($limit < 1 || $limit > 1000) {
            throw InvalidLimit::outOfRange('limit', $limit, 1, 1000);
        }

        $members = [];
        $scanned = 0;

        foreach ($this->scan($segment, $after) as $member => $in) {
            $last = $member;

            if ($in) {
                $members[] = $member;
            }

            if (\count($members) === $limit || ++$scanned === $this->maxScanned) {
                return new MemberPage($members, $last);
            }
        }

        return new MemberPage($members, null);
    }

    /**
     * Every member of the segment now, kept as a list that never changes.
     *
     * @throws SegmentTooLarge when the audience holds more members than `maxMembers`
     */
    public function freeze(Segment $segment): FrozenList
    {
        $members = [];

        foreach ($this->walk($segment) as $member => $in) {
            if ($in) {
                $members[] = $member;
            }
        }

        return new FrozenList($segment->id, $segment->name, $this->writer->fingerprint($segment->rule), $this->clock->now(), $members);
    }

    /**
     * Every member of the audience in ascending order, with whether they belong to the segment.
     *
     * @return Generator<string, bool>
     *
     * @throws SegmentTooLarge when the audience holds more members than `maxMembers`
     */
    public function walk(Segment $segment): Generator
    {
        $read = 0;

        foreach ($this->scan($segment, null) as $member => $in) {
            if (++$read > $this->maxMembers) {
                throw SegmentTooLarge::members($segment->id, $this->maxMembers);
            }

            yield $member => $in;
        }
    }

    /**
     * @return Generator<string, bool>
     */
    private function scan(Segment $segment, ?string $after): Generator
    {
        do {
            $page = $this->audience->members($after, $this->pageSize);

            if (\count($page) > $this->pageSize) {
                throw PortContractViolated::size('Audience', \count($page), $this->pageSize);
            }

            foreach ($page as $member) {
                if (!Identifier::valid($member) || (null !== $after && strcmp($after, $member) >= 0)) {
                    throw PortContractViolated::order('Audience', $member);
                }

                $after = $member;
            }

            $excluded = array_flip($this->exclusions->excluded($page));

            foreach ($page as $member) {
                yield $member => !isset($excluded[$member]) && $segment->rule->evaluate(Facts::of([Segment::MEMBER => $member]))->passed;
            }
        } while (\count($page) === $this->pageSize);
    }
}
