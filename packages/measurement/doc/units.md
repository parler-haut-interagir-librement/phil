# Units and families

A unit belongs to one family, the quantity it measures, and converts into every other unit of its family through the
family's standard unit: `standard = value × factor`. The families and units are data, read by `UnitRegistry` — there
is no class per quantity.

## The declared families

`UnitRegistry::standard()` holds the families of `data/units.php`, with factors that are the exact definitions of each
unit: the international inch, foot, yard and mile, the international avoirdupois pound (0.45359237 kg), the US
customary and imperial (4.54609 L) volumes, the thermochemical calorie (4.184 J).

| Family | Standard unit | Other units |
|---|---|---|
| `length` | `m` | `km`, `dm`, `cm`, `mm`, `um`, `[in_i]`, `[ft_i]`, `[yd_i]`, `[mi_i]`, `[nmi_i]` |
| `mass` | `kg` | `g`, `mg`, `t`, `[lb_av]`, `[oz_av]`, `[stone_av]`, `[ston_av]`, `[ozt_tr]`, `[car_m]` |
| `volume` | `m3` | `L`, `dL`, `cL`, `mL`, `cm3`, `[cin_i]`, `[cft_i]`, `[gal_us]`, `[qt_us]`, `[pt_us]`, `[cup_us]`, `[foz_us]`, `[tbs_us]`, `[tsp_us]`, `[gal_br]`, `[pt_br]`, `[foz_br]` |
| `area` | `m2` | `cm2`, `km2`, `har`, `[sin_i]`, `[sft_i]`, `[syd_i]`, `[acr_us]` |
| `temperature` | `K` | `Cel`, `[degF]`, `[degR]` |
| `temperature-difference` | `K{difference}` | `Cel{difference}`, `[degF]{difference}`, `[degR]{difference}` |
| `duration` | `s` | `ms`, `min`, `h`, `d`, `wk` |
| `speed` | `m/s` | `km/h`, `[mi_i]/h`, `[kn_i]` |
| `fuel-consumption` | `L/(100.km)` | `L/km`, `[mi_i]/[gal_us]`, `[mi_i]/[gal_br]`, `km/L` |
| `energy` | `J` | `kJ`, `cal`, `kcal`, `[Cal]`, `W.h`, `kW.h` |
| `pressure` | `Pa` | `hPa`, `kPa`, `mbar`, `bar`, `[psi]`, `mm[Hg]` |
| `power` | `W` | `kW` |

The gallon of `[gal_us]` and of `[gal_br]` differ by a fifth: a supplier's "gallon" is named by its code, never
guessed.

## Codes

A unit's code is its identifier in the registry, in the case-sensitive syntax of
[UCUM](https://ucum.org/ucum): `kg`, `[lb_av]`, `Cel`. It is also the code a product feed or another system exchanges
(`Unit::$ucum`), except for a unit that differs from another only by its family: a temperature difference carries the
annotation `{difference}`, which UCUM ignores, so `Cel{difference}` is exchanged as `Cel`.

```php
$units->unit('[lb_av]');                       // by code
$units->fromUcum('[lb_av]');                   // by exchange code
$units->fromUcum('Cel', 'temperature');        // a code of two families needs its family
```

`fromUcum()` refuses a code shared by units of several families unless the family is named.

## Adding units and families

`withDefinitions()` returns a new registry with families added, and units added to the families it holds:

```php
$units = UnitRegistry::standard()->withDefinitions([
    'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
    'paper-weight' => [
        'standard' => 'g/m2',
        'units' => ['g/m2' => ['factor' => '1'], '[lb_av]/[ream]' => ['factor' => '1.48']],
    ],
]);
```

A family is named by lowercase letters, digits and dashes. It gives:

| Key | Meaning |
|---|---|
| `standard` | The code of its standard unit, whose factor is 1; required for a new family, unchangeable afterwards. |
| `units` | Its units, by code: 1 to 64 printable ASCII characters, without spaces. |
| `cldr` | Its category in the CLDR data (`mass`, `consumption`), which its preferences are read from. |
| `difference` | The family that measures its differences, for an affine family such as `temperature`. |

A unit gives:

| Key | Meaning |
|---|---|
| `factor` | Its factor to the standard unit, a decimal or a fraction of integers (`5/9`), above zero. Required. |
| `offset` | The offset added after the factor, for a temperature scale: `standard = value × factor + offset`. |
| `reciprocal` | `true` for a unit that varies the other way: `standard = factor ÷ value`. |
| `ucum` | Its exchange code, when it differs from its code. |
| `cldr` | Its key in the CLDR data (`mass-grain`), for its patterns and preferences. |
| `difference` | The code of its unit of differences, required in an affine family, with the same factor. |

The registry refuses a code defined twice, a family that changes the settings of one it holds, and a definition with
a key it does not know: definitions read from a configuration are checked, never trusted. A registry holds 64 families
and 1,024 units at most, so that definitions read from outside cannot grow without end. A registry is immutable and
safe to share between requests.
