<!-- phil-dev source-sha256=c9c48ce5daed3ac314ebf50af91d55d84942563f93ce01044fbd8ff0c50d5858 -->
# Conversions

## Valeurs exactes

Une `Measurement` est une valeur et une unité. La valeur est lue à partir d'une chaîne décimale d'au plus
18 chiffres significatifs — `'12.5'`, `'-0.25'`, `'1200'` — et conservée sous la forme d'une fraction de deux entiers
natifs, jamais d'un nombre à virgule flottante :

```php
$weight = $units->measurement('2.75', '[lb_av]');
$weight = $units->parse('2.75 [lb_av]');            // as toString() writes it
```

Une conversion multiplie et divise la fraction par les facteurs des deux unités, qui sont aussi des fractions : une
chaîne de conversions conserve tous les chiffres intermédiaires, et la valeur est arrondie une seule fois, au moment
de son écriture.

```php
$weight->convertTo($units->unit('kg'))
    ->convertTo($units->unit('[oz_av]'))
    ->convertTo($units->unit('[lb_av]'))
    ->exactDecimal();                                // "2.75"
```

Quand la fraction exacte d'une étape ne tient plus dans des entiers natifs — une pression en livres par pouce carré,
dont le facteur a quatorze chiffres, multipliée par une valeur de six décimales —, cette étape est arrondie à
18 chiffres significatifs, ou à 18 chiffres après la virgule pour une valeur inférieure à un, par de l'arithmétique
entière seulement. Une valeur dont la partie entière ne tient pas dans un entier natif (environ 9,2 × 10¹⁸) est
refusée avec `MeasurementOutOfRange`.

## Écrire une valeur

| Méthode | Donne |
|---|---|
| `toDecimal(3)` | La valeur arrondie à 3 chiffres au plus après la virgule, zéros finaux retirés : `0.454`. |
| `toFixedDecimal(2)` | Exactement 2 chiffres après la virgule : `2.50`. |
| `exactDecimal()` | Le nombre décimal exact, ou `null` quand il ne finit jamais : un kilogramme en livres. |
| `round(2)` | Une mesure arrondie à 2 chiffres, dans son unité. |
| `toString()` | `<decimal> <code>`, exact jusqu'à 18 chiffres significatifs, que `UnitRegistry::parse()` relit. |

Chacune accepte un `RoundingMode`, à la moitié vers le pair par défaut.

## Arithmétique

Les mesures d'une famille s'additionnent, se soustraient, se comparent et se multiplient par un nombre, dans
n'importe laquelle de ses unités ; le résultat est dans l'unité de la mesure de gauche :

```php
$units->measurement('1.5', 'kg')->add($units->measurement('250', 'g'));     // 1.75 kg
$units->measurement('1.5', 'kg')->multiply('1/3');                          // 0.5 kg
$units->measurement('1', '[lb_av]')->compareTo($units->measurement('16', '[oz_av]'));   // 0
```

Les mesures de deux familles ne se mélangent jamais : `IncompatibleUnits` est lancée.

## Températures et leurs différences

Une échelle de température a un décalage : 0 °C vaut 273,15 K. Une température n'est donc pas une grandeur à
additionner, et une différence de températures se mesure dans une famille à part, `temperature-difference`, sans le
décalage :

```php
$morning = $units->measurement('10', 'Cel');
$morning->add($units->measurement('9', '[degF]{difference}'));        // 15 Cel
$units->measurement('68', '[degF]')->subtract($morning);              // 18 [degF]{difference}
$morning->add($morning);                                              // IncompatibleUnits
```

Une température accepte une différence, et une température moins une température est une différence. Une température
n'est ni niée ni multipliée.

## Unités réciproques

La consommation de carburant s'écrit en litres aux 100 km dans la plus grande partie du monde et en milles au gallon
aux États-Unis : l'une monte quand l'autre descend. Une unité réciproque se convertit selon
`standard = factor ÷ value`, et une comparaison lit l'unité standard, de sorte que 30 milles au gallon est au-dessus
de 40, puisqu'on consomme davantage :

```php
$units->measurement('30', '[mi_i]/[gal_us]')->convertTo($units->unit('L/(100.km)'))->toDecimal(2);   // "7.84"
```

Zéro n'a pas de correspondant dans une unité réciproque et est refusé. Une famille qui contient une unité réciproque
convertit et compare, mais ses mesures ne s'additionnent pas.

## Répartir sur plusieurs unités

Une taille s'écrit en pieds et en pouces, le poids d'une personne en stones et en livres :

```php
$units->measurement('70.5', '[in_i]')->split($units->unit('[ft_i]'), $units->unit('[in_i]'));
// [5 [ft_i], 10.5 [in_i]]

$units->measurement('179.9', 'cm')->splitRounded([$units->unit('[ft_i]'), $units->unit('[in_i]')], 0);
// [5 [ft_i], 11 [in_i]]
```

Chaque partie sauf la dernière est entière ; la dernière prend ce qui reste, exactement. `splitRounded()` arrondit
d'abord la mesure aux chiffres de sa dernière unité, de sorte que les parties n'affichent jamais 12 pouces.
