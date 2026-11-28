<!-- phil-dev source-sha256=6c7604a028795dc6a23fc2460d9b6ca107da9143a109040493e25120e27930b0 -->
# Canaux, préférences et mise en forme

## L'unité de chaque canal de vente

Une vitrine affiche les poids en kilogrammes, le flux d'une place de marché en livres, un point de vente en grammes.
`ChannelConversions` contient l'unité dans laquelle chaque canal affiche une famille :

```php
$conversions = new ChannelConversions($units, [
    'storefront' => ['mass' => 'kg', 'length' => 'cm'],
    'marketplace' => ['mass' => '[lb_av]', 'length' => '[in_i]'],
]);

$conversions->convert($weight, 'marketplace');      // in pounds, exactly
$conversions->unitFor('storefront', 'mass');        // the Unit, or null
```

Un canal sans unité pour une famille laisse ses mesures telles quelles. Une unité d'une autre famille que celle pour
laquelle elle est donnée est refusée à la construction des conversions.

## Préférences par région et par usage

L'unité dans laquelle une région écrit une quantité dépend de ce qu'on mesure : la taille d'une personne en pieds et en
pouces aux États-Unis et en mètres et en centimètres en France, une distance routière en milles au Royaume-Uni, un
colis en livres et en onces aux États-Unis. `UnitPreferences` lit ces choix dans les préférences d'unités de CLDR :

```php
$preferences = UnitPreferences::cldr($units);

$preferences->preferred($height, usage: 'person-height', region: 'US', fractionDigits: 0);   // [5 [ft_i], 10 [in_i]]
$preferences->preferred($trip, usage: 'road', region: 'GB', fractionDigits: 1);              // [26.1 [mi_i]]
$preferences->preferred($parcel, usage: 'default', region: 'US', fractionDigits: 2);         // [5.07 [lb_av]]
$preferences->units($parcel, 'default', 'US');                                               // the Unit list
```

Les candidats d'un usage sont essayés dans l'ordre : le premier que l'ordre de grandeur de la mesure atteint depuis
son seuil — à partir de 1 quand CLDR n'en donne pas — est choisi, et le dernier sinon ; un gramme de farine aux
États-Unis s'écrit en onces, une tonne de gravier en tonnes courtes. Un usage se replie sur son parent
(`person-height` sur `person`) puis sur `default` ; une région sans préférences propres prend celles du monde, `001`.
Une unité préférée que le registre ne contient pas est ignorée, et une famille sans catégorie CLDR garde sa mesure
telle quelle.

Un usage est formé de mots en minuscules joints par des tirets, et une région est un code ISO 3166-1 alpha-2 ou un
numéro UN M49 ; tout le reste est refusé avant la lecture des données.

## Écrire une mesure dans une locale

`UnitFormatter` écrit une mesure avec les motifs d'unités de CLDR, en trois largeurs :

```php
$formatter = UnitFormatter::cldr();

$formatter->format($weight, 'fr_CA', UnitWidth::Long);        // 1 234,5 kilogrammes
$formatter->format($weight, 'en', UnitWidth::Short);          // 1,234.5 kg
$formatter->format($weight, 'en', UnitWidth::Narrow, 1);      // 1,234.5kg
$formatter->formatParts($height, 'fr_CA', UnitWidth::Long);   // 5 pieds et 10 pouces
$formatter->unitName('mass-kilogram', 'fr');                  // kilogrammes
```

La valeur est arrondie à `$maximumFractionDigits`, 2 par défaut et 18 au plus, sous forme de chaîne décimale, et
écrite avec les symboles de la locale fournis par ICU : la partie entière par `NumberFormatter`, qui la groupe comme le
fait la locale, les décimales après le séparateur décimal de la locale et dans les chiffres de la locale. Aucun nombre à
virgule flottante ne porte les chiffres. Le motif est choisi par la catégorie de pluriel du nombre arrondi, qu'ICU lit à
partir de lui — le seul endroit où apparaît un nombre à virgule flottante, pour un choix entre `one`, `few` et
`other`. Les parties d'une mesure répartie sont jointes comme la locale joint des unités, avec les motifs de liste
d'ICU.

L'extension intl ne met des unités en forme que par les squelettes de nombres de `MessageFormatter`, dont l'argument est
un nombre à virgule flottante : la bibliothèque met en forme avec les motifs de CLDR à la place, de sorte que la valeur
écrite est la valeur exacte.

Une locale se replie sur son parent — `fr_CH` sur `fr`, `zh_Hant_TW` sur `zh_Hant` — puis sur `en` ; une locale
qu'ICU ne connaît pas prend les symboles de `en`. Une unité sans motifs CLDR, celle d'une application, s'écrit sous la
forme de sa valeur et de son code : `12 W.h`. Une locale est vérifiée avant de nommer un fichier. Les motifs et les
formateurs ICU de 64 locales au plus sont conservés pendant la vie du formateur : au-delà, ils sont relus, de sorte que
des locales tirées de requêtes ne puissent pas faire croître la mémoire d'un worker.

## Les données de CLDR

`data/cldr/` contient ce que la bibliothèque lit d'une version de [CLDR](https://cldr.unicode.org/), la version nommée
dans `data/cldr/VERSION` :

| Fichier | Contient |
|---|---|
| `preferences.php` | Les préférences d'unités, par catégorie, usage et région, d'après `unitPreferenceData`. |
| `patterns/<locale>.php` | Les noms d'affichage et les motifs de chaque unité, par largeur et par catégorie de pluriel, pour 37 locales. |
| `LICENSE` | La licence Unicode v3, sous laquelle les données sont distribuées. |

Les fichiers sont générés, jamais modifiés à la main. `bin/import-cldr.php` les réécrit à partir d'une copie de travail
de [cldr-json](https://github.com/unicode-org/cldr-json) à une étiquette de version :

```sh
php bin/import-cldr.php path/to/cldr-json 49.0.0 ar ca cs da de de-CH el en en-AU en-CA en-GB en-IN es es-419 es-MX fi fr fr-CA fr-CH he hi hu it ja ko nb nl pl pt pt-PT ro ru sv tr uk zh zh-Hant
```

Seules les catégories que déclare la bibliothèque sont conservées. Une locale hors de la liste se replie sur son
parent ou sur `en`.
