<!-- phil-dev source-sha256=b0503f174342f5695bcee4b0af2c4779e41bc9963b7210d2169298e64f282710 -->
# phil/measurement

**Poids, dimensions et volumes exacts, convertis d'une unité à l'autre sans dérive de nombre à virgule flottante,
affichés par canal de vente et écrits comme l'attendent chaque région et chaque langue.**

phil/measurement est une bibliothèque PHP simple : une mesure est une valeur décimale exacte et une unité, `12.5 kg`,
jamais un nombre à virgule flottante. Les unités appartiennent à des familles — longueur, masse, volume, température —
déclarées en données, qu'une application étend par configuration ; leurs codes sont des codes UCUM, ceux qu'on échange
avec d'autres systèmes. La bibliothèque convertit exactement entre les unités d'une famille, donne à chaque canal de
vente l'unité dans laquelle il affiche une famille, choisit l'unité dans laquelle une région écrit une quantité à
partir des préférences de CLDR, et écrit une mesure dans une locale à partir des motifs d'unités de CLDR.

```php
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\UnitRegistry;

$units = UnitRegistry::standard();
$weight = $units->measurement('2.75', '[lb_av]');

$weight->convertTo($units->unit('kg'))->toDecimal(3);              // "1.247"
$weight->convertTo($units->unit('[oz_av]'))->exactDecimal();       // "44"

$height = UnitPreferences::cldr($units)->preferred($units->measurement('178', 'cm'), 'person-height', 'US', 0);
UnitFormatter::cldr()->formatParts($height, 'en', UnitWidth::Long);  // "5 feet, 10 inches"
UnitFormatter::cldr()->format($weight, 'fr_CA', UnitWidth::Long);    // "2,75 livres"
```

## État

| Partie | État |
|---|---|
| Familles et unités en données, étendues par configuration, avec des codes UCUM | Implémenté et testé |
| Valeurs et conversions exactes, températures et leurs différences, unités réciproques | Implémenté et testé |
| L'unité de chaque canal de vente | Implémenté et testé |
| Les préférences d'unités de CLDR par région et par usage, mesures réparties sur plusieurs unités | Implémenté et testé |
| Écriture d'une mesure dans une locale à partir des motifs d'unités de CLDR | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Unités et familles](doc/fr/units.md) | Les familles et les unités que déclare la bibliothèque, les codes UCUM et l'ajout d'unités. |
| [Conversions](doc/fr/conversions.md) | Valeurs exactes, conversions, températures et leurs différences, unités réciproques, arrondi. |
| [Canaux, préférences et mise en forme](doc/fr/cldr.md) | L'unité de chaque canal de vente, les préférences de CLDR, l'écriture d'une mesure dans une locale et les données de CLDR. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/measurement
```

```php
$units = Phil\Measurement\UnitRegistry::standard()->withDefinitions([
    'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
]);

$units->parse('7000 [gr]')->convertTo($units->unit('[lb_av]'))->toString();   // "1 [lb_av]"
```

## Prérequis

PHP 8.5 ou une version plus récente, avec les extensions `intl` et `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

Les données de CLDR sous `data/cldr/` sont © Unicode, Inc., sous la licence Unicode v3, dont le texte est dans
[data/cldr/LICENSE](data/cldr/LICENSE).
