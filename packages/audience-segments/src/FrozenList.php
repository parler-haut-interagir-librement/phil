<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use DateTimeImmutable;
use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\Rules\Exception\InvalidLimit;

/**
 * The members of a segment as they were at a date, kept with a message as the proof of who it was sent to: opaque
 * identifiers in ascending byte order, with the fingerprint of the rule that selected them. Unlike the segment, it
 * never changes; an application stores it as it is.
 */
final readonly class FrozenList
{
    /**
     * @param list<string> $members distinct identifiers in ascending byte order
     *
     * @throws InvalidSegment when an identifier is out of form or the members are not sorted and distinct
     */
    public function __construct(
        public string $segment,
        public string $name,
        public string $fingerprint,
        public DateTimeImmutable $frozenAt,
        public array $members,
    ) {
        Identifier::check('segment', $segment);
        $previous = null;

        foreach ($members as $member) {
            Identifier::check('member', $member);

            if (null !== $previous && strcmp($previous, $member) >= 0) {
                throw InvalidSegment::unsortedMembers();
            }

            $previous = $member;
        }
    }

    public function count(): int
    {
        return \count($this->members);
    }

    public function contains(string $member): bool
    {
        $index = $this->firstAtOrAfter($member);

        return $index < \count($this->members) && $this->members[$index] === $member;
    }

    /**
     * Up to `$limit` members after `$after`, the first ones when it is null.
     *
     * @return list<string>
     */
    public function page(?string $after, int $limit): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw InvalidLimit::outOfRange('limit', $limit, 1, 1000);
        }

        if (null === $after) {
            return \array_slice($this->members, 0, $limit);
        }

        $start = $this->firstAtOrAfter($after);

        if ($start < \count($this->members) && $this->members[$start] === $after) {
            ++$start;
        }

        return \array_slice($this->members, $start, $limit);
    }

    /**
     * The index of the first member not before the identifier, by binary search.
     */
    private function firstAtOrAfter(string $id): int
    {
        $low = 0;
        $high = \count($this->members);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if (strcmp($this->members[$middle], $id) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}
