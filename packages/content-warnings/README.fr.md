<!-- phil-dev source-sha256=ca0aa0b90fc5818cbde2908b186aa5a97d0c06a1c509a4fb1f4c6123d6c0c90b -->
# phil/content-warnings

**Des avertissements de contenu pour les jeux, les livres, les articles et les événements, et les thèmes que chaque
lecteur replie ou masque.**

phil/content-warnings décrit ce qu'un contenu peut avoir de difficile à aborder et laisse chaque lecteur décider de ce
qui lui arrive. Un catalogue énumère les thèmes d'avertissement — gore, araignées, suicide, lumières clignotantes —,
regroupés en catégories et libellés en plusieurs langues. La bibliothèque est fournie avec une liste de base d'environ
soixante-dix thèmes en douze catégories, libellés en anglais, en français, en espagnol, en allemand, en italien et en
portugais. Chaque application la complète, en modifie les libellés, en retire des thèmes ou la remplace par sa propre
liste. Un contenu porte ses avertissements, un par thème, chacun avec une sévérité et une note facultatives. Les
préférences d'un lecteur indiquent, par thème, par catégorie ou par défaut, si un contenu visé par un avertissement est
affiché, replié derrière son avertissement ou masqué. Le comparateur réunit les deux en une décision par contenu.

Elle s'adresse à toute application qui publie du contenu que certains lecteurs préfèrent aborder avec précaution, ou pas
du tout : un catalogue de jeux, une bibliothèque, un blogue, un calendrier d'événements, un outil de sécurité dont les
lignes masquent et dont les voiles replient. C'est une simple bibliothèque PHP, sans autre dépendance que les extensions
JSON et multioctet de PHP. Dans une application Symfony, tournez-vous vers `phil/content-warnings-bundle`, qui ajoute la
configuration, la colonne Doctrine, les champs de formulaire, l'affichage replié dans Twig et un magasin de préférences.

La conception sépare les clés et les libellés. Les avertissements et les préférences désignent les thèmes et les
catégories par des clés stables : les données stockées survivent donc à un libellé reformulé, et un thème qu'une
application retire reste sur le contenu qui le nomme. Chaque valeur est immuable et bornée : 100 avertissements par
contenu, 2 000 thèmes par catalogue.

## État

| Partie | État |
|---|---|
| `Catalogue`, `Category`, `Theme`, `LocalizedText` | Implémenté et testé |
| `CatalogueLoader` : des définitions tirées de tableaux et de fichiers PHP et JSON, fusionnées ou en remplacement | Implémenté et testé |
| `BaseCatalogue` : la liste de base en six langues | Implémenté et testé |
| `ContentWarning`, `ContentWarnings`, `Severity`, conservés sous forme de tableau | Implémenté et testé |
| `WarningPreferences`, `Preference`, `Display`, conservés sous forme de tableau | Implémenté et testé |
| `WarningMatcher` et `Decision` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Catalogue](doc/fr/catalogue.md) | Les thèmes, les catégories, les libellés, la liste de base et les définitions qui la complètent ou la remplacent. |
| [Avertissements](doc/fr/warnings.md) | Les avertissements d'un contenu, leur sévérité et leur note, et leur stockage. |
| [Préférences et décisions](doc/fr/preferences.md) | Les préférences d'un lecteur, la façon dont un avertissement trouve son affichage et la décision pour un contenu. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application choisit, complète ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses bancs d'essai et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/content-warnings
```

```php
use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\CatalogueLoader;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningMatcher;
use Phil\ContentWarnings\WarningPreferences;

$catalogue = new CatalogueLoader()->apply(['remove' => ['clowns']], BaseCatalogue::load());

$warnings = ContentWarnings::of(
    new ContentWarning('gore', Severity::Severe, 'The final battle'),
    new ContentWarning('spiders'),
);

$preferences = WarningPreferences::defaults(Display::Show)
    ->withCategory('phobias', Display::Fold)
    ->withTheme('gore', Display::Hide, Severity::Moderate);

$decision = new WarningMatcher($catalogue)->decide($warnings, $preferences);
$decision->display;                          // Display::Hide
$catalogue->theme('spiders')?->label('fr');  // "Araignées"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-json` et `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
