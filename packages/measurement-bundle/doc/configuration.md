# Configuration

The bundle's configuration lives under `phil_measurement`:

```yaml
# config/packages/phil_measurement.yaml
phil_measurement:
    units:
        mass:
            units:
                '[gr]': { factor: '0.00006479891', cldr: mass-grain }
        paper-weight:
            standard: g/m2
            units:
                g/m2: { factor: '1' }
                '[lb_av]/[ream]': { factor: '1.48' }
    channels:
        storefront: { mass: kg, length: cm }
        marketplace: { mass: '[lb_av]', length: '[in_i]' }
    doctrine_type: true
```

| Option | Default | What it does |
|---|---|---|
| `units` | `{}` | Families and units added to those of the library, by family name, as `UnitRegistry::withDefinitions()` reads them. |
| `units.<family>.standard` | `null` | The standard unit of a new family. |
| `units.<family>.cldr` | `null` | The CLDR category of a new family, for its preferences. |
| `units.<family>.difference` | `null` | The family of differences of a new affine family. |
| `units.<family>.units.<code>.factor` | required | The factor to the standard unit, a decimal or a fraction such as `'5/9'`, as a string. |
| `units.<family>.units.<code>.offset` | `null` | The offset of a temperature scale. |
| `units.<family>.units.<code>.reciprocal` | `false` | A unit that varies the other way. |
| `units.<family>.units.<code>.ucum` | `null` | Its exchange code, when it differs from its code. |
| `units.<family>.units.<code>.cldr` | `null` | Its CLDR key, for its patterns and preferences. |
| `units.<family>.units.<code>.difference` | `null` | Its unit of differences, in an affine family. |
| `channels` | `{}` | The unit each sales channel shows a family in, by channel then family. |
| `doctrine_type` | `true` | Registers the `phil_measurement` column type with Doctrine DBAL, when it is installed. |

Unit codes and family names are kept as written: a code such as `[lb_av]` is quoted in YAML, and a dash is never turned
into an underscore. Factors are strings, so that YAML never reads them as floats.

The definitions and the channels are checked when the container is built: a unit defined twice, a new family without
its standard unit, or a channel's unit outside its family fails the build with the reason, rather than a request.

The "Units and families" document of `phil/measurement` lists the units it declares and what each key of a
definition means.
