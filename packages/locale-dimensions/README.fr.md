<!-- phil-dev source-sha256=9be19ee95c1a852222812890f48932c67c8c403076a86337372baa424c7135af -->
# phil/locale-dimensions

**Des locales, des versions et des chaînes de repli déclarées entre locales, en objets valeur simples.**

phil/locale-dimensions donne à une application les trois choses dont a besoin chaque fiche traduite ou versionnée, et
qu'il est facile de rater de peu : une `Locale` à une seule graphie (`fr`, `fr_CA`), une `Version` comptée à partir de
un, et une `FallbackChain` — les locales essayées, dans l'ordre, quand la locale demandée n'a rien à servir.
`DeclaredLocales` construit les chaînes à partir du tableau que déclare une application, en refusant une locale de
repli qu'elle ne sert pas.

Elle existe parce que les mêmes questions reviennent dans chaque paquet qui sert plus d'une langue : les pages de
contenu, les champs traduits d'un produit ou d'un événement, un plan de site et ses variantes `hreflang`, les textes
d'une interface. Si chacun y répond seul, `fr-CA` et `fr_CA` finissent en deux lignes, une traduction manquante tombe
dans la langue par défaut alors que la variante régionale est à une ligne de là, et une version zéro veut dire « rien
encore » à un endroit et une vraie ligne à un autre. Un seul jeu d'objets valeur les garde d'accord.

C'est une librairie PHP simple ; sa seule dépendance est `symfony/translation`, dont `LocaleFallbackProvider` dérive la
chaîne par défaut d'une locale qui n'en déclare aucune. Elle ne conserve rien et ne lit aucune requête : un bundle lit les
locales déclarées dans sa configuration, l'application choisit la locale demandée, et la chaîne dit quoi servir.

## État

| Partie | État |
|---|---|
| `Locale`, `Version` | Implémentées et testées |
| `FallbackChain`, `DeclaredLocales` | Implémentées et testées |
| `Exception\InvalidLocaleDimension` | Implémentée et testée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Référence](doc/fr/reference.md) | Chaque type public : la graphie d'une locale, la numérotation des versions, les chaînes et leur résolution, les locales déclarées, les refus. |
| [Points d'extension](doc/fr/extension-points.md) | Comment un bundle déclare ses locales, et comment une lecture parcourt une chaîne. |
| [Tests](doc/fr/testing.md) | Comment la librairie est testée. |

## Démarrage rapide

```php
use Phil\LocaleDimensions\{DeclaredLocales, Locale, Version};

$locales = DeclaredLocales::fromArray([
    'fr_CA' => ['fr', 'en'],
    'fr' => ['en'],
    'en_CA' => null,                                      // the default chain: en_CA → en
    'en' => [],
]);

$chain = $locales->chainFor(Locale::fromString('fr_CA'));
$chain->toString();                                       // 'fr_CA → fr → en'
$served = $chain->resolve([Locale::fromString('en'), Locale::fromString('fr')]);   // fr
$chain->leavesRequestedLanguage($served);                 // false: still French
$served->toLanguageTag();                                 // 'fr', for hreflang
Locale::tryFromString('fr-CA');                           // null: not a locale

Version::first()->next()->number;                         // 2
```

## Prérequis

PHP 8.5 ou une version plus récente et `symfony/translation` 8.2 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
