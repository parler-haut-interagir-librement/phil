<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Condition;

use Phil\AudienceSegments\Identifier;
use Phil\AudienceSegments\Segment;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;

/**
 * Reads the member every audience condition is about.
 *
 * @internal
 */
final class Member
{
    private function __construct()
    {
    }

    /**
     * The member's identifier, or the failing verdict when the scope holds none.
     */
    public static function of(FactSource $facts): string|Verdict
    {
        $member = $facts->has(Segment::MEMBER) ? $facts->get(Segment::MEMBER) : null;

        return \is_string($member) && Identifier::valid($member) ? $member : Verdict::fail('member.missing');
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function verdict(bool $holds, string $pass, string $fail, array $details): Verdict
    {
        return $holds ? Verdict::pass($pass, $details) : Verdict::fail($fail, $details);
    }
}
