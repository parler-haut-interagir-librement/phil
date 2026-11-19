<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use Closure;

/**
 * The columns a day is shared in, and which of them an item belongs to: its hosts, its venue, its tags. An item that
 * belongs to none goes to the `other` column when there is one, and is not shown otherwise.
 */
final readonly class Resources
{
    /**
     * @param list<CalendarResource>      $resources
     * @param Closure(Item): list<string> $keysOf
     */
    private function __construct(
        public array $resources,
        private Closure $keysOf,
        public ?CalendarResource $other,
    ) {
    }

    /**
     * A column per host, by `kind:reference`, labelled as the application names them.
     *
     * @param array<int|string, string> $labels by venue identifier|string, string> $labels by `kind:reference`
     */
    public static function hosts(array $labels, ?string $other = null): self
    {
        return self::keyed($labels, static fn (Item $item): array => $item->hosts, $other);
    }

    /**
     * A column per venue, by its identifier.
     *
     * @param array<int|string, string> $labels by venue identifier|string, string> $labels by venue
     */
    public static function venues(array $labels, ?string $other = null): self
    {
        return self::keyed($labels, static fn (Item $item): array => null === $item->venue ? [] : [$item->venue], $other);
    }

    /**
     * A column per tag.
     *
     * @param array<int|string, string> $labels by venue identifier|string, string> $labels by tag
     */
    public static function tags(array $labels, ?string $other = null): self
    {
        return self::keyed($labels, static fn (Item $item): array => $item->tags, $other);
    }

    /**
     * The columns an item goes in.
     *
     * @return list<CalendarResource>
     */
    public function of(Item $item): array
    {
        $keys = ($this->keysOf)($item);
        $found = array_values(array_filter($this->resources, static fn (CalendarResource $resource): bool => \in_array($resource->key, $keys, true)));

        return [] === $found && $this->other instanceof CalendarResource ? [$this->other] : $found;
    }

    /**
     * Every column, the `other` one last.
     *
     * @return list<CalendarResource>
     */
    public function columns(): array
    {
        return $this->other instanceof CalendarResource ? [...$this->resources, $this->other] : $this->resources;
    }

    /**
     * @param array<int|string, string>   $labels by venue identifier|string, string>   $labels
     * @param Closure(Item): list<string> $keysOf
     */
    private static function keyed(array $labels, Closure $keysOf, ?string $other): self
    {
        $resources = [];

        foreach ($labels as $key => $label) {
            $resources[] = new CalendarResource((string) $key, $label);
        }

        return new self($resources, $keysOf, null === $other ? null : new CalendarResource('', $other));
    }
}
