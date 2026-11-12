<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a site measures beyond its page views: what its pages report, and the events its server counts itself. Every
 * list is closed and bounded, so the work of one request stays bounded and a forged value creates no counter.
 */
final readonly class MeasurePlan
{
    public const int MAX_GOALS = 64;

    public const int MAX_DIMENSIONS = 16;

    /**
     * The longest server event name, so that the metric `server_events.<event>` stays a counter name.
     */
    public const int MAX_SERVER_EVENT_LENGTH = 50;

    /**
     * @param list<Goal>              $goals
     * @param list<DeclaredDimension> $dimensions
     */
    private function __construct(
        public ClickTracking $clicks,
        public ClosedList $clickNames,
        public ClosedList $forms,
        public ClosedList $downloads,
        public array $goals,
        public array $dimensions,
        public ClosedList $serverEvents,
    ) {
    }

    /**
     * @param list<Goal>              $goals
     * @param list<DeclaredDimension> $dimensions
     */
    public static function of(
        ClickTracking $clicks = ClickTracking::Aggregate,
        ?ClosedList $clickNames = null,
        ?ClosedList $forms = null,
        ?ClosedList $downloads = null,
        array $goals = [],
        array $dimensions = [],
        ?ClosedList $serverEvents = null,
    ): self {
        self::assertBounded('goals', $goals, self::MAX_GOALS);
        self::assertBounded('dimensions', $dimensions, self::MAX_DIMENSIONS);

        foreach ($serverEvents?->toArray() ?? [] as $event) {
            if (1 !== preg_match(CounterKey::NAME_PATTERN, $event) || \strlen($event) > self::MAX_SERVER_EVENT_LENGTH) {
                throw InvalidValue::because(\sprintf('"%s" cannot be a server event: it starts with a letter and holds at most %d characters.', $event, self::MAX_SERVER_EVENT_LENGTH));
            }
        }

        return new self($clicks, $clickNames ?? ClosedList::empty(), $forms ?? ClosedList::empty(), $downloads ?? ClosedList::downloads(), $goals, $dimensions, $serverEvents ?? ClosedList::empty());
    }

    /**
     * @param list<Goal>|list<DeclaredDimension> $declared
     */
    private static function assertBounded(string $kind, array $declared, int $limit): void
    {
        if (\count($declared) > $limit) {
            throw InvalidValue::because(\sprintf('A site declares at most %d %s; this one declares %d.', $limit, $kind, \count($declared)));
        }

        $names = array_map(static fn (Goal|DeclaredDimension $item): string => $item->name, $declared);

        if (\count(array_unique($names)) !== \count($names)) {
            throw InvalidValue::because(\sprintf('Two %s share a name.', $kind));
        }
    }
}
