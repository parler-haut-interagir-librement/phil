# Extension points

The bundle is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add units or families | Add them under `units` in the configuration. |
| Choose the unit of a channel | Add it under `channels`. |
| Replace the registry, the preferences or the formatter | Decorate the service `Phil\Measurement\UnitRegistry`, `Phil\Measurement\Cldr\UnitPreferences` or `Phil\Measurement\Cldr\UnitFormatter`; the form type, the validators, the filters and the column type take them. |
| Change a message | Give the constraint's message option, or translate its text in your `validators` domain. |
| Store a measurement in two columns | Map a `decimal` and a unit code: see [Doctrine](doctrine.md#two-columns-to-sort-or-sum-in-sql). |
| Do without the column type | Set `doctrine_type: false`. |
