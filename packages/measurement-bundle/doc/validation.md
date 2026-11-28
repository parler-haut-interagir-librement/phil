# Validation

## `MeasurementRange`

The measurement lies between `min` and `max`, both inclusive, written as `Measurement::toString()` writes them and
compared in any unit of their family: `max: '30 kg'` refuses 70 pounds.

```php
#[MeasurementRange(min: '0 kg', max: '30 kg')]
public ?Measurement $weight = null;
```

| Violation | Code | Message |
|---|---|---|
| Below `min` | `MeasurementRange::TOO_LOW_ERROR` | `This measurement should be {{ limit }} or more.` |
| Above `max` | `MeasurementRange::TOO_HIGH_ERROR` | `This measurement should be {{ limit }} or less.` |
| Of another family than the bounds | `MeasurementRange::FAMILY_ERROR` | `This measurement cannot be compared with {{ limit }}.` |

Either bound may be left out, not both. The bounds are read by the application's registry, so its own units may be
used; a bound it cannot read is a mistake of the constraint and throws `ConstraintDefinitionException`.

## `MeasurementUnit`

The measurement is of the `family` given, in one of the `units` given when there are some.

```php
#[MeasurementUnit(family: 'mass', units: ['g', 'kg'])]
public ?Measurement $weight = null;
```

| Violation | Code | Message |
|---|---|---|
| Of another family | `MeasurementUnit::FAMILY_ERROR` | `This value should be a measurement of {{ family }}.` |
| In a unit outside the list | `MeasurementUnit::UNIT_ERROR` | `The unit {{ unit }} is not accepted.` |

Each message is an option of its constraint, and is translated in English and French in the `validators` domain.
`null` is valid for both constraints, as for every constraint but `NotNull`.
