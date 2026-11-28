# Channels, preferences and formatting

## The unit of each sales channel

A storefront shows weights in kilograms, a marketplace feed in pounds, a point of sale in grams. `ChannelConversions`
holds the unit each channel shows a family in:

```php
$conversions = new ChannelConversions($units, [
    'storefront' => ['mass' => 'kg', 'length' => 'cm'],
    'marketplace' => ['mass' => '[lb_av]', 'length' => '[in_i]'],
]);

$conversions->convert($weight, 'marketplace');      // in pounds, exactly
$conversions->unitFor('storefront', 'mass');        // the Unit, or null
```

A channel without a unit for a family leaves its measurements as they are. A unit of another family than the one it is
given for is refused when the conversions are built.

## Preferences by region and usage

The unit a region writes a quantity in depends on what is measured: a person's height in feet and inches in the
United States and in metres and centimetres in France, a road distance in miles in the United Kingdom, a parcel in
pounds and ounces in the United States. `UnitPreferences` reads these choices from the unit preferences of CLDR:

```php
$preferences = UnitPreferences::cldr($units);

$preferences->preferred($height, usage: 'person-height', region: 'US', fractionDigits: 0);   // [5 [ft_i], 10 [in_i]]
$preferences->preferred($trip, usage: 'road', region: 'GB', fractionDigits: 1);              // [26.1 [mi_i]]
$preferences->preferred($parcel, usage: 'default', region: 'US', fractionDigits: 2);         // [5.07 [lb_av]]
$preferences->units($parcel, 'default', 'US');                                               // the Unit list
```

The candidates of a usage are tried in order: the first one the magnitude of the measurement reaches from its
threshold — from 1 when CLDR gives none — is chosen, and the last one otherwise; a gram of flour in the United States is
in ounces, a tonne of gravel in tons. A usage falls back to its parent (`person-height` to `person`) and then to
`default`; a region without preferences of its own takes the world's, `001`. A preferred unit the registry does not
hold is skipped, and a family without a CLDR category keeps its measurement as it is.

A usage is lowercase words joined by dashes, and a region an ISO 3166-1 alpha-2 code or a UN M49 number; anything else
is refused before the data is read.

## Writing a measurement in a locale

`UnitFormatter` writes a measurement with the unit patterns of CLDR, in three widths:

```php
$formatter = UnitFormatter::cldr();

$formatter->format($weight, 'fr_CA', UnitWidth::Long);        // 1 234,5 kilogrammes
$formatter->format($weight, 'en', UnitWidth::Short);          // 1,234.5 kg
$formatter->format($weight, 'en', UnitWidth::Narrow, 1);      // 1,234.5kg
$formatter->formatParts($height, 'fr_CA', UnitWidth::Long);   // 5 pieds et 10 pouces
$formatter->unitName('mass-kilogram', 'fr');                  // kilogrammes
```

The value is rounded to `$maximumFractionDigits`, 2 by default and 18 at most, as a decimal string, and written with
the locale's symbols from ICU: the integer part through `NumberFormatter`, which groups it as the locale does, the
fraction digits after the locale's decimal separator and in the locale's digits. No float carries the digits. The
pattern is chosen by the plural category of the rounded number, which ICU reads from it — the one place a float
appears, for a choice between `one`, `few` and `other`. The parts of a split measurement are joined as the locale joins
units, with the list patterns of ICU.

The intl extension formats units only through the number skeletons of `MessageFormatter`, whose argument is a float:
the library formats on the CLDR patterns instead, so that the value written is the exact one.

A locale falls back to its parent — `fr_CH` to `fr`, `zh_Hant_TW` to `zh_Hant` — and then to `en`; a locale that ICU
does not know takes the symbols of `en`. A unit without CLDR patterns, an application's own, is written as its value
and its code: `12 W.h`. A locale is checked before it names a file. The patterns and the ICU formatters of 64 locales
at most are kept for the life of the formatter: past them, they are read again, so that locales taken from requests
cannot grow a worker's memory.

## The CLDR data

`data/cldr/` holds what the library reads of a release of [CLDR](https://cldr.unicode.org/), the release named in
`data/cldr/VERSION`:

| File | Holds |
|---|---|
| `preferences.php` | The unit preferences, by category, usage and region, from `unitPreferenceData`. |
| `patterns/<locale>.php` | The display names and patterns of each unit, by width and plural category, for 37 locales. |
| `LICENSE` | The Unicode License v3, under which the data is distributed. |

The files are generated, never edited by hand. `bin/import-cldr.php` writes them again from a checkout of
[cldr-json](https://github.com/unicode-org/cldr-json) at a release tag:

```sh
php bin/import-cldr.php path/to/cldr-json 49.0.0 ar ca cs da de de-CH el en en-AU en-CA en-GB en-IN es es-419 es-MX fi fr fr-CA fr-CH he hi hu it ja ko nb nl pl pt pt-PT ro ru sv tr uk zh zh-Hant
```

Only the categories the library declares are kept. A locale outside the list falls back to its parent or to `en`.
