<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Entitlement;

use DateInterval;
use DateTimeImmutable;
use Phil\ContentAccess\Exception\InvalidGrant;

/**
 * A right a person holds: a feature name, such as `episodes:early` or `course:intro`, from a start until an optional
 * end, and the source that gave it, such as a subscription, a purchase or an enrolment. A content names features; it
 * never knows where they come from.
 */
final readonly class Grant
{
    public const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,127}$/D';

    /**
     * @throws InvalidGrant when the feature or the source is not an identifier, or the end does not follow the start
     */
    public function __construct(
        public string $feature,
        public DateTimeImmutable $since,
        public ?DateTimeImmutable $until = null,
        public ?string $source = null,
    ) {
        if (1 !== preg_match(self::IDENTIFIER_PATTERN, $feature)) {
            throw InvalidGrant::identifier('feature');
        }

        if (null !== $source && 1 !== preg_match(self::IDENTIFIER_PATTERN, $source)) {
            throw InvalidGrant::identifier('source');
        }

        if ($until instanceof DateTimeImmutable && $until <= $since) {
            throw InvalidGrant::endsBeforeItStarts();
        }
    }

    /**
     * Whether the grant holds at that instant: from its start, included, to its end, excluded.
     */
    public function activeAt(DateTimeImmutable $at): bool
    {
        return $this->since <= $at && (!$this->until instanceof DateTimeImmutable || $at < $this->until);
    }

    /**
     * The instant that many calendar days after the start, in the start's time zone.
     */
    public function daysAfterStart(int $days): DateTimeImmutable
    {
        return $this->since->add(new DateInterval(\sprintf('P%dD', max(0, $days))));
    }
}
