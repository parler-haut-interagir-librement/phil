<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Exception\UnknownMeter;

/**
 * The meters an application declares, by name.
 */
final readonly class Meters
{
    /** @var array<string, Meter> */
    private array $meters;

    public function __construct(Meter ...$meters)
    {
        $byName = [];

        foreach ($meters as $meter) {
            if (isset($byName[$meter->name])) {
                throw InvalidUsage::because(\sprintf('The meter "%s" is declared twice.', $meter->name));
            }

            $byName[$meter->name] = $meter;
        }

        $this->meters = $byName;
    }

    public function get(string $name): Meter
    {
        return $this->meters[$name] ?? throw UnknownMeter::named($name);
    }

    public function has(string $name): bool
    {
        return isset($this->meters[$name]);
    }

    /**
     * @return list<string> in the order they were declared
     */
    public function names(): array
    {
        return array_values(array_map(static fn (Meter $meter): string => $meter->name, $this->meters));
    }
}
