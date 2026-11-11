<!-- phil-dev source-sha256=d1c3f4b9e026e2b2ee315afb7ab2a4c7a528903b561e77db659b4c99e0a6d241 -->
# phil/opening-hours-bundle

**Un formulaire d'heures d'ouverture, une colonne JSON et des heures localisées dans Twig, pour Symfony.**

phil/opening-hours-bundle intègre les heures de `phil/opening-hours` à Symfony. `OpeningHoursType` a un champ pour
chaque jour, nommé dans la langue de la requête, où l'on saisit les plages du jour sous la forme
`09:00-12:00, 13:00-17:00`. Il ajoute un champ pour les heures spéciales, une période par ligne, et un choix de fuseau
horaire. Un champ illisible est invalide à lui seul, avec un message qui montre comment le remplir. Le type de colonne
Doctrine `phil_opening_hours` garde les heures dans une seule colonne JSON. Dans Twig, des fonctions disent si le lieu
est ouvert maintenant et quand il ouvre de nouveau. D'autres écrivent la semaine et les heures spéciales à venir, et
donnent l'`openingHoursSpecification` de schema.org. Un gabarit de tableau affiche la semaine. Les libellés et les
messages existent en anglais et en français.

Il s'adresse aux applications Symfony où un éditeur ou un administrateur saisit les heures d'un lieu et où des
visiteurs les lisent. Les jours, les dates et les heures sont écrits par ICU dans la langue de la requête : les mêmes
heures se lisent *lundi – vendredi* pour un visiteur et *Monday – Friday* pour un autre.

Hors de Symfony, utilisez `phil/opening-hours` seul. Le bundle ajoute ce qui exige le cadriciel ou `ext-intl` : le
formulaire, le type de colonne, la formulation d'ICU et une horloge PSR-20 pour « maintenant ».

## État

| Partie | État |
|---|---|
| `OpeningHoursType` | Implémenté et testé |
| Le type de colonne `phil_opening_hours` | Implémenté et testé |
| Twig : `opening_hours_open`, `_next`, `_week`, `_specials`, `_schema` ; le gabarit de tableau | Implémenté et testé |
| Libellés et messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, `default_timezone`. |
| [Formulaires](doc/fr/forms.md) | `OpeningHoursType` et ses options. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_opening_hours`. |
| [Twig](doc/fr/twig.md) | Les fonctions et le gabarit de tableau. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/opening-hours-bundle
```

```php
#[ORM\Column(type: 'phil_opening_hours', nullable: true)]
private ?OpeningHours $hours = null;

$builder->add('hours', OpeningHoursType::class);
```

```twig
{% include '@PhilOpeningHours/table.html.twig' with {hours: shop.hours} %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` et `ext-mbstring` ; `phil/opening-hours` 0.1 ; `psr/clock` 1 ;
`symfony/config`, `symfony/dependency-injection`, `symfony/form` et `symfony/options-resolver` 8.2 ou plus récent.
Doctrine DBAL et Twig sont facultatifs. Avec Twig, le conteneur a besoin d'un service `clock`, que FrameworkBundle
enregistre dès que `symfony/clock` est installé : sans lui, le conteneur ne se compile pas.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
