# Reference

## Classes

| Class | Role |
|---|---|
| `PhilMeasurementBundle` | The bundle, its configuration `phil_measurement`, and the registration of the column type when it boots. |
| `Infrastructure\Form\MeasurementType` | The form type of a measurement: `family`, `units`, `unit`, `channel`. |
| `Infrastructure\Form\MeasurementTransformer` | Between a `Measurement` and the `value` and `unit` fields, in the request's locale. |
| `Infrastructure\Validator\Attribute\MeasurementRange` | The constraint of a measurement between two bounds. |
| `Infrastructure\Validator\MeasurementRangeValidator` | Its validator, which reads the bounds with the registry. |
| `Infrastructure\Validator\Attribute\MeasurementUnit` | The constraint of a measurement's family and unit. |
| `Infrastructure\Validator\MeasurementUnitValidator` | Its validator. |
| `Infrastructure\Twig\MeasurementExtension` | The filters `format_measurement`, `measurement_in_channel` and `preferred_units`. |
| `Infrastructure\Doctrine\MeasurementType` | The `phil_measurement` column type. |

## Services

| Service | Class | Role |
|---|---|---|
| `Phil\Measurement\UnitRegistry` | `UnitRegistry` | The library's units with those of `units`; public as `phil_measurement.registry`. |
| `phil_measurement.standard_registry` | `UnitRegistry` | The library's units alone, which the registry above extends. |
| `Phil\Measurement\ChannelConversions` | `ChannelConversions` | The units of `channels`. |
| `Phil\Measurement\Cldr\UnitPreferences` | `UnitPreferences` | The CLDR preferences, with the registry. |
| `Phil\Measurement\Cldr\UnitFormatter` | `UnitFormatter` | The CLDR patterns. |
| `Phil\MeasurementBundle\Infrastructure\Form\MeasurementType` | | Tagged `form.type`. |
| `…\MeasurementRangeValidator`, `…\MeasurementUnitValidator` | | Tagged `validator.constraint_validator`. |
| `Phil\MeasurementBundle\Infrastructure\Twig\MeasurementExtension` | | Tagged `twig.attribute_extension` and `twig.runtime`. |

## Parameters

| Parameter | Value |
|---|---|
| `phil_measurement.doctrine_type` | The `doctrine_type` option. |

## Translations

`translations/validators.en.php` and `translations/validators.fr.php` hold the messages of the form type and the
constraints.
