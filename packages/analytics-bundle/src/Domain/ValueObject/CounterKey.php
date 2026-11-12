<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a counter counts: a site, a UTC day, a metric, and one value of one declared dimension.
 *
 * **A counter is the only thing stored.** No visitor identifier, address, cookie or device identifier belongs in
 * the key. This type checks the shape of a key — the names, a value bounded in length and free of control
 * characters — and **cannot tell an identifier from a dimension value**: keeping each dimension to a closed list
 * of values is the application's, and a value that would only ever match one person is what that list exists
 * to prevent.
 */
final readonly class CounterKey
{
    /**
     * The value every counter that is not shown on its own is gathered under: a path that matches no declared
     * route at ingestion, and the read-side grouping of counters under the small-number threshold.
     */
    public const string OTHER = '(other)';

    /**
     * The value a dimension takes when a page view carries none, such as the country of an unknown address.
     */
    public const string UNKNOWN = '(unknown)';

    /**
     * Names of metrics, dimensions and routes.
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    public const int MAX_VALUE_LENGTH = 128;

    private function __construct(
        public SiteId $site,
        public UtcDay $day,
        public string $metric,
        public string $dimension,
        public string $value,
    ) {
    }

    public static function of(SiteId $site, UtcDay $day, string $metric, string $dimension, string $value): self
    {
        foreach (['metric' => $metric, 'dimension' => $dimension] as $kind => $name) {
            if (1 !== preg_match(self::NAME_PATTERN, $name)) {
                throw InvalidValue::because(\sprintf('"%s" is not a %s name: 1 to 64 characters among a-z, 0-9, "_", "." and "-", starting with a letter.', $name, $kind));
            }
        }

        if ('' === $value) {
            throw InvalidValue::because('A dimension value cannot be empty.');
        }

        if (\strlen($value) > self::MAX_VALUE_LENGTH) {
            throw InvalidValue::because(\sprintf('A dimension value is at most %d bytes; this one is %d.', self::MAX_VALUE_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidValue::because('A dimension value cannot contain control characters.');
        }

        return new self($site, $day, $metric, $dimension, $value);
    }

    public function equals(self $other): bool
    {
        return $this->site->equals($other->site)
            && $this->day->equals($other->day)
            && $this->metric === $other->metric
            && $this->dimension === $other->dimension
            && $this->value === $other->value;
    }
}
