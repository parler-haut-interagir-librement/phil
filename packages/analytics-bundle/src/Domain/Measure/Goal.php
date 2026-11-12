<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Something a site wants its visitors to reach: a declared route, or a measured event of a declared value.
 */
final readonly class Goal
{
    public const int MAX_NAME_LENGTH = 32;

    private function __construct(
        public string $name,
        private ?string $route,
        private ?MeasureEvent $event,
        private ?string $value,
    ) {
    }

    public static function onRoute(string $name, string $route): self
    {
        return new self(self::checked($name), $route, null, null);
    }

    public static function onEvent(string $name, MeasureEvent $event, string $value): self
    {
        return new self(self::checked($name), null, $event, $value);
    }

    public function isReachedByRoute(string $route): bool
    {
        return $route === $this->route;
    }

    public function isReachedByEvent(MeasureEvent $event, string $value): bool
    {
        return $event === $this->event && $value === $this->value;
    }

    private static function checked(string $name): string
    {
        if (\strlen($name) > self::MAX_NAME_LENGTH || 1 !== preg_match(CounterKey::NAME_PATTERN, $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not a goal name: 1 to %d characters among a-z, 0-9, "_", "." and "-", starting with a letter.', $name, self::MAX_NAME_LENGTH));
        }

        return $name;
    }
}
