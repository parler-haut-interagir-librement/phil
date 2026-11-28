<?php

declare(strict_types=1);

namespace Phil\Measurement;

use Phil\Measurement\Exception\InvalidUnitDefinition;

/**
 * The unit each sales channel shows a family in: a storefront in Canada in kilograms and centimetres, a marketplace
 * feed in pounds and inches, a point of sale in grams.
 *
 * ```php
 * $conversions = new ChannelConversions($registry, [
 *     'storefront' => ['mass' => 'kg', 'length' => 'cm'],
 *     'marketplace' => ['mass' => '[lb_av]', 'length' => '[in_i]'],
 * ]);
 * $conversions->convert($weight, 'marketplace');   // in pounds, exactly
 * ```
 *
 * A channel without a unit for a family leaves its measurements as they are.
 */
final readonly class ChannelConversions
{
    /** @var array<array-key, array<string, Unit>> */
    private array $units;

    /**
     * @param array<array-key, array<string, string>> $units the unit code by family, by channel; a channel named by digits is an integer key
     */
    public function __construct(UnitRegistry $registry, array $units)
    {
        $resolved = [];

        foreach ($units as $channel => $families) {
            foreach ($families as $family => $code) {
                $unit = $registry->unit($code);

                if ($unit->family !== $registry->family($family)->name) {
                    throw InvalidUnitDefinition::because(\sprintf('The unit "%s" of the channel "%s" is not a unit of the family "%s".', $code, $channel, $family));
                }

                $resolved[$channel][$family] = $unit;
            }
        }

        $this->units = $resolved;
    }

    public function unitFor(string $channel, string $family): ?Unit
    {
        return $this->units[$channel][$family] ?? null;
    }

    /**
     * The measurement in the channel's unit for its family, or as it is when the channel has none.
     */
    public function convert(Measurement $measurement, string $channel): Measurement
    {
        $unit = $this->unitFor($channel, $measurement->unit->family);

        return $unit instanceof Unit ? $measurement->convertTo($unit) : $measurement;
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        return array_map(strval(...), array_keys($this->units));
    }
}
