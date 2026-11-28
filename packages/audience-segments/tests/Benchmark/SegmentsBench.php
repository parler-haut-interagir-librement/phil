<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Benchmark;

use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\Contract\Participation;
use Phil\AudienceSegments\Contract\PeopleTags;
use Phil\AudienceSegments\Segment;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Segments;
use Phil\AudienceSegments\Tests\Support\FixedClock;
use Phil\AudienceSegments\Tests\Support\MemoryAudience;
use Phil\AudienceSegments\Tests\Support\MemoryExclusions;

/**
 * The hot path of a send: a segment of two conditions frozen over an audience of 5 000 members, read by pages of 500,
 * with a hundred exclusions.
 */
final readonly class SegmentsBench
{
    private Segment $segment;

    private Segments $segments;

    public function __construct()
    {
        $members = [];

        for ($i = 0; $i < 5000; ++$i) {
            $members[] = \sprintf('person:%05d', $i);
        }

        // Ports that answer from the identifier, as an indexed query would, without recording anything.
        $facts = new class implements Participation, PeopleTags {
            public function holdsTicket(string $member, string $event, array $ticketTypes): bool
            {
                return 0 === (int) substr($member, 7) % 3;
            }

            public function attended(string $member, string $occurrence): bool
            {
                return false;
            }

            public function inSelection(string $member, string $selection): bool
            {
                return false;
            }

            public function hasAnyTag(string $member, array $tags): bool
            {
                return 0 === (int) substr($member, 7) % 7;
            }
        };

        $exclusions = array_map(static fn (int $i): string => \sprintf('person:%05d', $i * 50), range(0, 99));
        $this->segment = new SegmentJson(AudienceConditions::types(participation: $facts, tags: $facts))->read(
            'fair',
            '{"version":1,"name":"Fair buyers","condition":{"all":[{"type":"ticket.holder","operands":{"event":"spring-fair"}},{"not":{"type":"person.tagged","operands":{"tags":["crew"]}}}]}}',
        );
        $this->segments = new Segments(new MemoryAudience(...$members), new MemoryExclusions(...$exclusions), new FixedClock());
    }

    public function benchFreezeASegment(): int
    {
        return $this->segments->freeze($this->segment)->count();
    }
}
