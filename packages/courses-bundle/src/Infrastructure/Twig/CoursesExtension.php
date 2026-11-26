<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Twig;

use NumberFormatter;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Symfony\Component\Intl\Currencies;
use Twig\Attribute\AsTwigFunction;

/**
 * The functions of the bundle's templates: `phil_courses_kit()`, the name under which a recipe of the kit is
 * installed, and `phil_courses_price()`, a price as the locale writes it — with `ext-intl`, else its amount and currency
 * code —, its decimals those `symfony/intl` gives the currency, two without it.
 */
final readonly class CoursesExtension
{
    public function __construct(
        private KitComponents $kit,
    ) {
    }

    /**
     * `{% component (phil_courses_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_courses_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    /**
     * `{{ phil_courses_price(entry.price, app.request.locale) }}`: `$12.50` in `en`, `12,50 $` in `fr-CA`.
     */
    #[AsTwigFunction(name: 'phil_courses_price')]
    public function price(CoursePrice $price, string $locale = 'en'): string
    {
        $digits = class_exists(Currencies::class) && Currencies::exists($price->currency) ? Currencies::getFractionDigits($price->currency) : 2;
        $amount = $price->minorUnits / 10 ** $digits;

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $digits);
            $written = $formatter->formatCurrency($amount, $price->currency);

            if (false !== $written) {
                return $written;
            }
        }

        return number_format($amount, $digits, '.', '').' '.$price->currency;
    }
}
