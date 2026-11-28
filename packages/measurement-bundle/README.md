# phil/measurement-bundle

**Weights and sizes in Symfony forms, validation, Twig and Doctrine, as exact decimals with their units, per sales
channel, written in each locale.**

phil/measurement-bundle integrates `phil/measurement` with Symfony: the registry of units with the application's own,
the unit of each sales channel, a form type that reads a value and a unit in the request's locale, constraints that
bound a measurement in any unit of its family, Twig filters that write it in the locale or in the unit a region
prefers, and a Doctrine column type that stores it without a float. The library alone suits an application without
Symfony.

```php
#[ORM\Column(type: 'phil_measurement')]
#[MeasurementRange(min: '0 kg', max: '30 kg')]
#[MeasurementUnit(family: 'mass')]
public ?Measurement $weight = null;

$builder->add('weight', MeasurementType::class, ['family' => 'mass', 'channel' => 'storefront']);
```

```twig
{{ product.weight|format_measurement('long') }}                     {# 12,5 kilogrammes #}
{{ person.height|preferred_units('person-height')|format_measurement }}
```

## Status

| Part | State |
|---|---|
| `PhilMeasurementBundle` and its configuration `phil_measurement`: units added, unit of each channel | Implemented and tested |
| `MeasurementType`, the form type of a value and a unit | Implemented and tested |
| `MeasurementRange` and `MeasurementUnit`, the constraints | Implemented and tested |
| `format_measurement`, `measurement_in_channel` and `preferred_units`, the Twig filters | Implemented and tested |
| `phil_measurement`, the Doctrine column type | Implemented and tested on SQLite; tests for MySQL and PostgreSQL run when their servers are given |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Forms](doc/forms.md) | `MeasurementType`, its options and how it reads a value. |
| [Validation](doc/validation.md) | `MeasurementRange` and `MeasurementUnit`. |
| [Twig](doc/twig.md) | The filters that write and convert a measurement. |
| [Doctrine](doc/doctrine.md) | The `phil_measurement` column type, and two columns to sort or sum in SQL. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\MeasurementBundle\PhilMeasurementBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_measurement.yaml
phil_measurement:
    channels:
        storefront: { mass: kg, length: cm }
        marketplace: { mass: '[lb_av]', length: '[in_i]' }
```

## Requirements

PHP 8.5 or later with the `intl` extension, and Symfony 8.2 or later. Doctrine DBAL 4 for the column type, Twig 3 for
the filters.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
