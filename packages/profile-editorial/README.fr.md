<!-- phil-dev source-sha256=82db3b18739ab05dbf8dae81aee2a1d28cddfa86f04e34f6c0bf3a8d7b016f5e -->
# phil/profile-editorial

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/profile-editorial/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/profile-editorial)

**Un seul `composer require` pour les paquets PHIL qui publient du contenu éditorial dans une application Symfony :
du texte écrit dans plusieurs balisages et affiché en HTML sûr, des citations et des bibliographies, des notes de bas de
page et des notes marginales, des avertissements de contenu que les lecteurs contrôlent, et les données schema.org que
lisent les moteurs de recherche.**

phil/profile-editorial est un métapaquet Composer : il ne contient aucun code. L'exiger installe cinq paquets PHIL
souvent employés ensemble par les sites qui publient des articles, des livres, des jeux ou des pages d'événements.
Chaque membre reste un paquet indépendant, avec sa propre configuration, sa propre documentation et ses propres
versions : le profil ne couple aucun service à un autre, il évite seulement de les chercher un à un et de choisir des
contraintes qui fonctionnent ensemble.

Il s'adresse aux applications Symfony dont les auteurs écrivent du contenu long et dont les lecteurs s'attendent à le
trouver lisible, sourcé et honnête sur ce qu'il contient. Une application qui n'a besoin que d'une de ces parties
exige ce membre seul.

## Ce qu'il installe

| Membre | Contrainte | Apporte | Pourquoi il est ici |
|---|---|---|---|
| `phil/markup-bundle` | `^0.1` | `phil/markup` 0.2, `phil/ujo`, `phil/svg-sanitizer`, `phil/css-sanitizer` | Les auteurs écrivent en Markdown, en BBCode, en syntaxes wiki, en UJO (Universal JSON Output) ou en HTML ; les lecteurs reçoivent du HTML purifié, au niveau que choisit l'application. |
| `phil/citations` | `^0.1` | rien de plus | Des références conservées en CSL-JSON, importées depuis BibTeX et RIS, et écrites selon les styles auteur-date, notes ou numérique, en anglais ou en français. |
| `phil/footnotes-sidenotes` | `^0.1` | `phil/ujo` | Les notes de bas de page et les notes marginales des documents UJO, numérotées et vérifiées, affichées en notes de fin accessibles, en notes latérales ou au bas des pages imprimées. |
| `phil/content-warnings-bundle` | `^0.1` | `phil/content-warnings` | Des avertissements tirés d'un catalogue de thèmes traduit, sur n'importe quel contenu, repliés ou masqués selon le choix de chaque lecteur. |
| `phil/structured-data-bundle` | `^0.1` | `phil/structured-data`, `phil/indexing-consent`, `phil/url` | Un graphe schema.org par page, imprimé en JSON-LD de façon sûre, et omis des pages retirées des moteurs de recherche. |

La page des [membres](doc/fr/members.md) donne le rôle de chacun, ce qu'il ajoute à l'application et ce dont il a
besoin.

## Ce que chaque membre apporte à l'application

Aucun membre ne fournit de recette Symfony Flex : Flex enregistre chaque bundle dans `config/bundles.php` et ne copie
aucun fichier. Chaque membre fonctionne ensuite avec ses valeurs par défaut, et sa propre documentation dit quoi
configurer.

| Membre | Bundle enregistré | Fichiers qu'il fournit |
|---|---|---|
| `phil/markup-bundle` | `Phil\MarkupBundle\PhilMarkupBundle` | Les traductions de ses libellés |
| `phil/content-warnings-bundle` | `Phil\ContentWarningsBundle\PhilContentWarningsBundle` | Le gabarit `@PhilContentWarnings/fold.html.twig`, le contrôleur Stimulus `reveal` dans `assets/package.json`, des traductions en six langues |
| `phil/structured-data-bundle` | `Phil\StructuredDataBundle\PhilStructuredDataBundle` | La commande `phil:structured-data:check` |
| `phil/citations` | aucun : une bibliothèque | aucun |
| `phil/footnotes-sidenotes` | aucun : une bibliothèque | aucun |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | La stabilité qu'exige une alpha de PHIL, l'enregistrement des bundles sans Flex, ce qu'il faut configurer ensuite. |
| [Membres](doc/fr/members.md) | Chaque membre : son rôle, ce qu'il ajoute, ce dont il a besoin, sa documentation. |
| [Mise à niveau](doc/fr/upgrading.md) | Comment les contraintes évoluent, et comment se passer d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Où une application change le comportement : dans les membres. |

## Démarrage rapide

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-editorial
```

Pour se passer d'un membre — ici `phil/content-warnings-bundle` —, exigez directement les autres au lieu du profil :

```sh
composer remove phil/profile-editorial
composer require phil/markup-bundle:^0.1 phil/citations:^0.1 phil/footnotes-sidenotes:^0.1 phil/structured-data-bundle:^0.1
```

## Prérequis

PHP 8.5 ou une version plus récente et Symfony 8.2 ou une version plus récente, avec les extensions dont les membres
ont besoin : `ext-intl`, `ext-mbstring`, `ext-dom`, `ext-libxml`, `ext-ctype` et `ext-filter`. Les membres sont des
versions alpha : le projet les accepte avec `minimum-stability: alpha` et `prefer-stable: true`. Twig est nécessaire
pour les filtres, les fonctions et les gabarits des bundles.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
