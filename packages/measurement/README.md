# phil/measurement

**Weights, sizes and volumes kept exact, converted between units without float drift, shown per sales channel and
written as each region and language expects.**

phil/measurement is a plain PHP library: a measurement is an exact decimal value and a unit, `12.5 kg`, never a
float. Units belong to families — length, mass, volume, temperature — declared as data, which an application extends
by configuration; their codes are UCUM codes, the ones exchanged with other systems. The library converts between the
units of a family exactly, gives each sales channel the unit it shows a family in, picks the unit a region writes a
quantity in from the preferences of CLDR, and writes a measurement in a locale from the unit patterns of CLDR.

```php
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\UnitRegistry;

$units = UnitRegistry::standard();
$weight = $units->measurement('2.75', '[lb_av]');

$weight->convertTo($units->unit('kg'))->toDecimal(3);              // "1.247"
$weight->convertTo($units->unit('[oz_av]'))->exactDecimal();       // "44"

$height = UnitPreferences::cldr($units)->preferred($units->measurement('178', 'cm'), 'person-height', 'US', 0);
UnitFormatter::cldr()->formatParts($height, 'en', UnitWidth::Long);  // "5 feet, 10 inches"
UnitFormatter::cldr()->format($weight, 'fr_CA', UnitWidth::Long);    // "2,75 livres"
```

## Status

| Part | State |
|---|---|
| Families and units in data, extended by configuration, with UCUM codes | Implemented and tested |
| Exact values and conversions, temperatures and their differences, reciprocal units | Implemented and tested |
| The unit of each sales channel | Implemented and tested |
| The unit preferences of CLDR by region and usage, measurements split over several units | Implemented and tested |
| Writing a measurement in a locale from the unit patterns of CLDR | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Units and families](doc/units.md) | The families and units the library declares, UCUM codes, and adding units. |
| [Conversions](doc/conversions.md) | Exact values, conversions, temperatures and their differences, reciprocal units, rounding. |
| [Channels, preferences and formatting](doc/cldr.md) | The unit of each sales channel, the CLDR preferences, writing a measurement in a locale, and the CLDR data. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz targets and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/measurement
```

```php
$units = Phil\Measurement\UnitRegistry::standard()->withDefinitions([
    'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
]);

$units->parse('7000 [gr]')->convertTo($units->unit('[lb_av]'))->toString();   // "1 [lb_av]"
```

## Requirements

PHP 8.5 or later, with the `intl` and `mbstring` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

The CLDR data under `data/cldr/` is © Unicode, Inc., under the Unicode License v3, whose text is in
[data/cldr/LICENSE](data/cldr/LICENSE).
