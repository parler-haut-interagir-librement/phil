# Reference

## `Phil\Measurement`

| Type | Role |
|---|---|
| `UnitRegistry` | The families and units, built from definitions in data: `standard()`, `fromDefinitions()`, `withDefinitions()`; `unit()`, `family()`, `families()`, `has()`, `fromUcum()`, `measurement()`, `parse()`, `definitions()`. Immutable; 64 families and 1,024 units at most. |
| `UnitFamily` | A family: its `name`, its `standard` unit, its `units` by code (the standard one first), its CLDR category `cldr` and its family of differences `difference`; `has()`, `unitForCldr()`. |
| `Unit` | A unit: `code`, `family`, `ucum`, `cldr`, `reciprocal`, `additive`, its unit of differences `difference`; `factor()` and `offset()` written exactly, `isStandard()`. |
| `Measurement` | An exact value in a unit: `of()`, `convertTo()`, `add()`, `subtract()`, `multiply()`, `negate()`, `compareTo()`, `isZero()`, `isNegative()`, `round()`, `toDecimal()`, `toFixedDecimal()`, `exactDecimal()`, `split()`, `splitRounded()`, `toString()`. Immutable. |
| `ChannelConversions` | The unit each sales channel shows a family in: `convert()`, `unitFor()`, `channels()`. |
| `Fraction` | Internal: the exact number a measurement holds. |

## `Phil\Measurement\Cldr`

| Type | Role |
|---|---|
| `UnitPreferences` | The preferred units of a region for a usage, from CLDR: `cldr()`, `preferred()`, `units()`. |
| `UnitFormatter` | A measurement written in a locale from the CLDR patterns: `cldr()`, `format()`, `formatParts()`, `unitName()`. Keeps the data of 64 locales at most. |
| `UnitWidth` | `Long`, `Short` or `Narrow`, the widths of CLDR. |
| `CldrImporter` | Internal: writes `data/cldr/` from cldr-json, run by `bin/import-cldr.php`. |

## `Phil\Measurement\Exception`

Every exception implements `MeasurementException`; each one is a mistake of the caller, of its input or of its
definitions.

| Type | Thrown for |
|---|---|
| `InvalidMeasurement` | A decimal or a measurement that cannot be read, zero in a reciprocal unit, an invalid usage, region, locale or number of digits. |
| `IncompatibleUnits` | Two families mixed, two temperatures added, a fuel consumption summed. |
| `UnknownUnit` | A code, a UCUM code or a family the registry does not hold, or a UCUM code of two families without its family. |
| `InvalidUnitDefinition` | A definition the registry refuses, a unit of a channel outside its family, CLDR data that cannot be imported. |
| `MeasurementOutOfRange` | More than 18 significant digits, or a value whose integer part leaves the native integers. |
