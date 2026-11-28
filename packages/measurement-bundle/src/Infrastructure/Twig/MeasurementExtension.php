<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Twig;

use Locale;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\Measurement;
use Twig\Attribute\AsTwigFilter;

/**
 * Measurements in templates, which neither Twig nor its extra extensions write: `format_number` knows no unit.
 *
 * ```twig
 * {{ product.weight|format_measurement }}                                   {# 12,5 kg in fr_CA #}
 * {{ product.weight|format_measurement('long', 1) }}                        {# 12,5 kilogrammes #}
 * {{ product.weight|measurement_in_channel('marketplace')|format_measurement }}
 * {{ person.height|preferred_units('person-height')|format_measurement }}   {# 5 ft, 10 in in en_US #}
 * ```
 *
 * No measurement prints nothing. The locale is the request's unless given; the region of `preferred_units` is the
 * locale's, or the world (`001`) when the locale names none.
 */
final readonly class MeasurementExtension
{
    public function __construct(
        private UnitFormatter $formatter,
        private ChannelConversions $conversions,
        private UnitPreferences $preferences,
    ) {
    }

    /**
     * @param Measurement|list<Measurement>|null $measurement a measurement, or the parts of a split one
     */
    #[AsTwigFilter(name: 'format_measurement')]
    public function format(Measurement|array|null $measurement, string $width = 'short', int $maximumFractionDigits = 2, ?string $locale = null): string
    {
        if (null === $measurement || [] === $measurement) {
            return '';
        }

        $locale ??= Locale::getDefault();
        $width = UnitWidth::from($width);

        return $measurement instanceof Measurement
            ? $this->formatter->format($measurement, $locale, $width, $maximumFractionDigits)
            : $this->formatter->formatParts($measurement, $locale, $width, $maximumFractionDigits);
    }

    #[AsTwigFilter(name: 'measurement_in_channel')]
    public function inChannel(?Measurement $measurement, string $channel): ?Measurement
    {
        return $measurement instanceof Measurement ? $this->conversions->convert($measurement, $channel) : null;
    }

    /**
     * @return list<Measurement>
     */
    #[AsTwigFilter(name: 'preferred_units')]
    public function preferred(?Measurement $measurement, string $usage = 'default', ?string $region = null, ?int $fractionDigits = null): array
    {
        if (!$measurement instanceof Measurement) {
            return [];
        }

        $region ??= Locale::getRegion(Locale::getDefault());

        return $this->preferences->preferred($measurement, $usage, \is_string($region) && 1 === preg_match('/^[A-Z]{2}$/D', $region) ? $region : '001', $fractionDigits);
    }
}
