<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Benchmark;

use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;

/**
 * The hot paths of a product feed: a thousand weights converted to a channel's unit and written in a locale, the
 * registry built with an application's units, and the conversions whose exact fraction no longer fits.
 */
final readonly class ConversionBench
{
    private UnitRegistry $registry;

    private ChannelConversions $conversions;

    private UnitFormatter $formatter;

    private UnitPreferences $preferences;

    /** @var list<Measurement> */
    private array $weights;

    public function __construct()
    {
        $this->registry = UnitRegistry::standard();
        $this->conversions = new ChannelConversions($this->registry, ['marketplace' => ['mass' => '[lb_av]']]);
        $this->formatter = UnitFormatter::cldr();
        $this->preferences = UnitPreferences::cldr($this->registry);
        $this->weights = array_map(fn (int $grams): Measurement => $this->registry->measurement($grams.'.125', 'g'), range(1, 1_000));
    }

    public function benchThousandWeightsToAChannel(): void
    {
        foreach ($this->weights as $weight) {
            $this->conversions->convert($weight, 'marketplace')->toDecimal(2);
        }
    }

    public function benchThousandWeightsFormatted(): void
    {
        foreach ($this->weights as $weight) {
            $this->formatter->format($weight, 'fr_CA', UnitWidth::Short);
        }
    }

    public function benchThousandPreferredUnits(): void
    {
        foreach ($this->weights as $weight) {
            $this->preferences->preferred($weight, 'default', 'US', 2);
        }
    }

    public function benchThousandRoundedConversions(): void
    {
        $psi = $this->registry->unit('[psi]');
        $kilopascals = $this->registry->unit('kPa');

        foreach ($this->weights as $index => $weight) {
            $this->registry->measurement($index.'.123457', '[psi]')->convertTo($kilopascals)->convertTo($psi);
        }
    }

    public function benchRegistryWithApplicationUnits(): void
    {
        UnitRegistry::standard()->withDefinitions(['mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891']]]]);
    }
}
