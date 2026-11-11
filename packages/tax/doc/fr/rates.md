<!-- phil-dev source-sha256=f2a554e3aafcf49dd26d23d9e83a2f622d739324a95ab55efe64aae6c43488e3 -->
# Taux

Un `TaxRate` est un code et une part de la base, en parties par million.

| Membre | Comportement |
|---|---|
| `static of(string $code, int $partsPerMillion): self` | Code : 1 à 32 caractères, lettres majuscules, chiffres, `_` ou `-`, commençant par une lettre. Part : 0 à 1 000 000 ppm. Sinon `InvalidTaxRate` |
| `static ofPercent(string $code, string $percent): self` | La part à partir de son pourcentage en chaîne décimale, `"9.975"` : des chiffres, jusqu'à quatre décimales après `.`, de 0 à 100. Sinon `InvalidTaxRate` |
| `public string $code`, `public int $partsPerMillion` | La valeur |
| `const ONE_MILLION = 1_000_000` | 100 % |
| `percent(): string` | Le pourcentage sans zéros de fin : `"9.975"`, `"5"`, `"0.0001"` |
| `taxOn(Money $base): Money` | La taxe sur la base, dans la devise de la base, arrondie à la moitié en s'éloignant de zéro à l'unité mineure |
| `equals(self $other): bool` | Même code et même part |

```php
use Phil\Tax\TaxRate;

TaxRate::of('QST', 99_750)->percent();               // "9.975"
TaxRate::ofPercent('QST', '9.975')->partsPerMillion; // 99750
TaxRate::ofPercent('HST', '13.00')->percent();        // "13"
```

## Parties par million

Ni un nombre à virgule flottante, ni des points de base. 9,975 % a trois décimales en pourcentage : les points de base
ne peuvent pas le contenir, un nombre à virgule flottante ne peut pas le contenir exactement, `99_750` ppm le peut.
Quatre décimales en pourcentage correspondent à la précision d'une partie par million : `ofPercent()` refuse donc une
cinquième décimale plutôt que de l'arrondir.

## Le code

Ce qu'affiche un reçu et ce qu'inverse une note de crédit (`GST`, `QST`, `VAT`…). Les codes sont comparés exactement,
casse comprise ; une ventilation contient chaque code une seule fois.

## Arrondi

**À la moitié en s'éloignant de zéro, à l'unité mineure de la devise de la base.** `0.5` devient `1`, `-0.5` devient
`-1`. La taxe sur une base de signe opposé est donc exactement la taxe de signe opposé, ce sur quoi repose une note de
crédit. Le calcul se fait en entiers seulement : `(|base| × ppm + 500 000) ÷ 1 000 000`, puis le signe de la base.

La précision de la devise est celle de la base : 10 % de 1 000 yens font 100 yens, 10 % de 12,500 dinars font 1,250
dinar.

## La plus grande base

`|base| × ppm + 500 000` doit tenir dans un entier natif quelle que soit la part. Une base dont la valeur absolue
dépasse `intdiv(PHP_INT_MAX - 1_000_000, 1_000_000)` unités mineures — environ 92 milliards dans une devise à deux
décimales, sur une plateforme 64 bits — est donc refusée avec `UntaxableBase`. Tout ce qui reste en deçà est taxé
exactement.
