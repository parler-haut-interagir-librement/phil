<!-- phil-dev source-sha256=69e3652f45731e60ae10fff1cea85fb8771f26ca216d1d63d3f9e4725b8652dd -->
# Unités et familles

Une unité appartient à une famille, la grandeur qu'elle mesure, et se convertit dans chaque autre unité de sa famille
par l'unité standard de la famille : `standard = value × factor`. Les familles et les unités sont des données, lues
par `UnitRegistry` : il n'y a pas de classe par grandeur.

## Les familles déclarées

`UnitRegistry::standard()` contient les familles de `data/units.php`, avec des facteurs qui sont les définitions
exactes de chaque unité : le pouce, le pied, la verge et le mille internationaux, la livre avoirdupois internationale
(0,45359237 kg), les volumes du système usuel américain et du système impérial (4,54609 L), la calorie
thermochimique (4,184 J).

| Famille | Unité standard | Autres unités |
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

Le gallon de `[gal_us]` et celui de `[gal_br]` diffèrent d'un cinquième : le « gallon » d'un fournisseur est nommé
par son code, jamais deviné.

## Codes

Le code d'une unité est son identifiant dans le registre, dans la syntaxe sensible à la casse d'[UCUM](https://ucum.org/ucum) :
`kg`, `[lb_av]`, `Cel`. C'est aussi le code qu'échangent un flux de produits ou un autre système (`Unit::$ucum`),
sauf pour une unité qui ne diffère d'une autre que par sa famille : une différence de température porte l'annotation
`{difference}`, qu'UCUM ignore, de sorte que `Cel{difference}` s'échange sous la forme `Cel`.

```php
$units->unit('[lb_av]');                       // by code
$units->fromUcum('[lb_av]');                   // by exchange code
$units->fromUcum('Cel', 'temperature');        // a code of two families needs its family
```

`fromUcum()` refuse un code que partagent des unités de plusieurs familles à moins que la famille ne soit nommée.

## Ajouter des unités et des familles

`withDefinitions()` renvoie un nouveau registre avec des familles ajoutées, et des unités ajoutées aux familles qu'il
contient :

```php
$units = UnitRegistry::standard()->withDefinitions([
    'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
    'paper-weight' => [
        'standard' => 'g/m2',
        'units' => ['g/m2' => ['factor' => '1'], '[lb_av]/[ream]' => ['factor' => '1.48']],
    ],
]);
```

Une famille est nommée par des lettres minuscules, des chiffres et des tirets. Elle donne :

| Clé | Signification |
|---|---|
| `standard` | Le code de son unité standard, dont le facteur est 1 ; obligatoire pour une nouvelle famille, non modifiable ensuite. |
| `units` | Ses unités, par code : de 1 à 64 caractères ASCII imprimables, sans espaces. |
| `cldr` | Sa catégorie dans les données de CLDR (`mass`, `consumption`), dont ses préférences sont lues. |
| `difference` | La famille qui mesure ses différences, pour une famille affine comme `temperature`. |

Une unité donne :

| Clé | Signification |
|---|---|
| `factor` | Son facteur vers l'unité standard, un nombre décimal ou une fraction d'entiers (`5/9`), supérieur à zéro. Obligatoire. |
| `offset` | Le décalage ajouté après le facteur, pour une échelle de température : `standard = value × factor + offset`. |
| `reciprocal` | `true` pour une unité qui varie dans l'autre sens : `standard = factor ÷ value`. |
| `ucum` | Son code d'échange, quand il diffère de son code. |
| `cldr` | Sa clé dans les données de CLDR (`mass-grain`), pour ses motifs et ses préférences. |
| `difference` | Le code de son unité de différences, obligatoire dans une famille affine, avec le même facteur. |

Le registre refuse un code défini deux fois, une famille qui change les réglages d'une famille qu'il contient, et une
définition avec une clé qu'il ne connaît pas : les définitions lues dans une configuration sont vérifiées, jamais crues
sur parole. Un registre contient 64 familles et 1 024 unités au plus, afin que des définitions lues de l'extérieur ne
puissent pas croître sans fin. Un registre est immuable et peut être partagé sans risque entre des requêtes.
