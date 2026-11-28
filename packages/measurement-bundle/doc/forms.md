# Forms

`MeasurementType` is a `Measurement` in a form: a `value` text field in the request's locale and, when more than one
unit is offered, a `unit` choice labelled with the short CLDR names of the units in the request's locale.

```php
$builder->add('weight', MeasurementType::class, ['family' => 'mass']);                   // every unit of the family
$builder->add('weight', MeasurementType::class, ['units' => ['kg', 'g', '[lb_av]']]);
$builder->add('weight', MeasurementType::class, ['unit' => 'kg']);                       // one unit, no choice
$builder->add('weight', MeasurementType::class, ['family' => 'mass', 'channel' => 'marketplace']);
```

| Option | Default | What it does |
|---|---|---|
| `family` | `null` | The family whose units are offered. |
| `units` | `null` | The codes of the units offered, all of one family, in the order of the choice. |
| `unit` | `null` | One unit, offered without a choice; it wins over `units` and `family`. |
| `channel` | `null` | The sales channel whose unit an empty field starts on, when it is offered. |

One of `family`, `units` or `unit` is given; units of two families, or outside the `family` given, are refused when
the form is built. An empty field starts on the channel's unit for the family when it is offered, on the first offered
unit otherwise.

## Reading the value

The value is read as a string, never a float: digits, an optional `-` (or `−`), and the locale's decimal separator or
a dot followed by digits; spaces, no-break spaces included, are ignored. Nothing else is read, so `1,5` is never read
as fifteen, and a fraction such as `1/3` is refused. A value the form cannot read fails it with its `invalid_message`,
`This value is not a valid measurement.`, translated in English and French. An empty value is no measurement, `null`.

A measurement in a unit the form does not offer is shown in its default unit, converted exactly; one of another family
fails the form. A form with one unit takes the submitted value in it.
