<!-- phil-dev source-sha256=01875b5197adce1f1b2578ad4068193de11d9dda457a4a79b07ac1be55a81c71 -->
# Formulaires

`MeasurementType` est une `Measurement` dans un formulaire : un champ de texte `value` dans la locale de la requête et,
quand plus d'une unité est offerte, un choix `unit` dont les libellés sont les noms courts de CLDR des unités dans la
locale de la requête.

```php
$builder->add('weight', MeasurementType::class, ['family' => 'mass']);                   // every unit of the family
$builder->add('weight', MeasurementType::class, ['units' => ['kg', 'g', '[lb_av]']]);
$builder->add('weight', MeasurementType::class, ['unit' => 'kg']);                       // one unit, no choice
$builder->add('weight', MeasurementType::class, ['family' => 'mass', 'channel' => 'marketplace']);
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `family` | `null` | La famille dont les unités sont offertes. |
| `units` | `null` | Les codes des unités offertes, toutes d'une même famille, dans l'ordre du choix. |
| `unit` | `null` | Une seule unité, offerte sans choix ; elle l'emporte sur `units` et sur `family`. |
| `channel` | `null` | Le canal de vente dont l'unité sert de départ à un champ vide, quand elle est offerte. |

On donne l'une de `family`, `units` ou `unit` ; des unités de deux familles, ou hors de la `family` donnée, sont
refusées à la construction du formulaire. Un champ vide part de l'unité du canal pour la famille quand elle est offerte,
et de la première unité offerte sinon.

## Lire la valeur

La valeur est lue comme une chaîne, jamais comme un nombre à virgule flottante : des chiffres, un `-` facultatif (ou
`−`), puis le séparateur décimal de la locale ou un point suivi de chiffres ; les espaces, insécables comprises, sont
ignorées. Rien d'autre n'est lu, de sorte que `1,5` n'est jamais lu comme quinze, et qu'une fraction comme `1/3` est
refusée. Une valeur que le formulaire ne sait pas lire le fait échouer avec son `invalid_message`,
`This value is not a valid measurement.`, traduit en anglais et en français. Une valeur vide n'est pas une mesure,
`null`.

Une mesure dans une unité que le formulaire n'offre pas est affichée dans son unité par défaut, convertie exactement ;
celle d'une autre famille fait échouer le formulaire. Un formulaire à une seule unité reçoit la valeur soumise dans
cette unité.
