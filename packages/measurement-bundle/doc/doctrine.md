# Doctrine

## The `phil_measurement` column type

A `Measurement` in one string column, as its text: its exact decimal and its unit code, `12.5 kg`, never a float.

```php
#[ORM\Column(type: 'phil_measurement')]
public Measurement $weight;

#[ORM\Column(type: 'phil_measurement', length: 40, nullable: true)]
public ?Measurement $length = null;
```

The column holds 100 characters unless the mapping gives its `length`: a canonical decimal of 18 significant digits, a
space and a code of 64 bytes fit. The text is read back by the application's registry, so its own units are known; a
unit the registry no longer holds, or a text that is not a measurement, fails the hydration with Doctrine's
`ValueNotConvertible` rather than passing unchecked.

The bundle registers the type when it boots, built with the application's registry, unless `doctrine_type` is
`false`; it is not declared under `doctrine.dbal.types`, which would build it without its registry.

## Two columns to sort or sum in SQL

A text column sorts as text. A measurement to sort, filter or sum in SQL is mapped as two columns, a `decimal`, which
Doctrine gives as a string, and a unit code, in a unit chosen once for the column:

```php
#[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
private string $weightValue;

#[ORM\Column(length: 64)]
private string $weightUnit;

public function weight(UnitRegistry $units): Measurement
{
    return $units->measurement($this->weightValue, $this->weightUnit);
}

public function setWeight(Measurement $weight, UnitRegistry $units): void
{
    $inKilograms = $weight->convertTo($units->unit('kg'));
    $this->weightValue = $inKilograms->toFixedDecimal(6);
    $this->weightUnit = 'kg';
}
```

The value is rounded to the scale of the column when it is written: six digits of a kilogram is a milligram.
