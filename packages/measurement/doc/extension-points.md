# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add units to a family, or a new family | Pass definitions to `UnitRegistry::withDefinitions()`: see [Units and families](units.md#adding-units-and-families). |
| Replace the declared units | Build the registry with `UnitRegistry::fromDefinitions()` from your own definitions. |
| Choose the unit of each sales channel | Build `ChannelConversions` with your channels. |
| Use other unit preferences | Build `UnitPreferences` with your registry and an array of the shape of `data/cldr/preferences.php`. |
| Use other unit patterns | Build `UnitFormatter` with a directory of files of the shape of `data/cldr/patterns/<locale>.php`. |
| Import another CLDR release or other locales | Run `bin/import-cldr.php`: see [the CLDR data](cldr.md#the-cldr-data). |
| Handle every failure in one place | Catch `MeasurementException`. |

`Fraction` and `CldrImporter` are internal: they may change in any version.
