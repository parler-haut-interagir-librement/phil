<!-- phil-dev source-sha256=1cdc46467ae3b068b2a7b7b8c86cadb9cd763afa4d082b0f0bdac995adc3964d -->
# Répartition

Répartir un montant ne doit ni perdre ni créer d'unité : trois personnes qui partagent 1.00 paient 0.34, 0.33 et 0.33,
et les parts donnent de nouveau 1.00 une fois additionnées.

## `allocate(int ...$ratios): list<Money>`

Répartit le montant selon des proportions. Chaque part reçoit sa quote-part arrondie vers le bas ; les unités restantes
vont une à une aux parts dont la quote-part a été le plus arrondie vers le bas (le **plus fort reste**), la première
part l'emportant en cas d'égalité.

```php
$cad = Currency::of('CAD');

Money::of(100, $cad)->allocate(1, 1, 1);  // 34, 33, 33
Money::of(100, $cad)->allocate(1, 1, 5);  // 14, 14, 72: 71.43 was rounded down the most
Money::of(5, $cad)->allocate(3, 7);       // 2, 3: a tie goes to the earlier part
Money::of(5, $cad)->allocate(0, 1, 1);    // 0, 3, 2: a ratio of 0 receives nothing
Money::of(-100, $cad)->allocate(1, 1, 1); // -34, -33, -33
```

Une proportion est un nombre entier, nul ou positif ; au moins une proportion est donnée, et leur somme n'est pas nulle.
Une répartition en pourcentages s'écrit en proportions entières : `allocate(70, 30)`, ou `allocate(9_975, 5_000)` pour
des taux à décimales. Le résultat est une liste, quelles que soient les clés des proportions.

## `split(int $parts): list<Money>`

Répartit le montant en parts égales, les premières parts recevant les unités restantes.

```php
Money::of(100, $cad)->split(3);  // 34, 33, 33
Money::of(11, $cad)->split(3);   // 4, 4, 3
Money::of(-100, $cad)->split(3); // -34, -33, -33
```

## Refus

Tous sont des `InvalidAmount` : aucune proportion ; une proportion négative ; des proportions dont la somme est nulle ;
des proportions, ou le montant multiplié par une proportion, hors de l'entier natif ; moins d'une part.
