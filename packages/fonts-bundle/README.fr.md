<!-- phil-dev source-sha256=9793e19ecbe8b0055f9ffc26d935230c1290568a984137616d106d49a43608b0 -->
# phil/fonts-bundle

**Les fichiers de police d'une application Symfony, lus une seule fois : leurs règles `@font-face`, leurs licences
vérifiées par une commande, et les mêmes polices remises au générateur de PDF.**

phil/fonts-bundle intègre `phil/fonts` à une application Symfony. Il lit un répertoire de fichiers de police (TrueType,
OpenType, collections, WOFF et, quand `ext-brotli` est chargée, WOFF2) et en fait un catalogue de faces, avec leur
famille, leur graisse, leur style, leurs permissions d'intégration et leur licence. À partir de ce catalogue, le bundle
écrit les règles `@font-face` du répertoire pour une feuille de style ou un gabarit : une règle par face, avec chacun de
ses fichiers, du plus petit au plus gros. Il tient un registre des licences à côté des polices. Une commande qui peut
faire échouer une construction liste chaque fichier ajouté, retiré ou modifié depuis la dernière vérification de sa
licence. Enfin, le bundle remet les faces TrueType au générateur de PDF natif de `phil/paged-media` et lit les polices
téléversées dans `phil/media-bundle`.

Sans `ext-brotli`, les fichiers WOFF2 ne peuvent pas être lus : ils restent au catalogue avec cette raison, et les
commandes le disent clairement. Leurs équivalents WOFF, TrueType ou OpenType fournissent alors les métadonnées. Le
fichier WOFF2 reste servi en premier dans la règle `@font-face`, puisque le navigateur sait le lire.

Il s'adresse à toute application Symfony qui sert des polices qu'elle n'a pas dessinées : un site qui héberge lui-même
ses polices, une chaîne d'impression ou de livres numériques qui les intègre, une construction qui ne doit pas livrer
une police dont personne n'a vérifié la licence.

C'est un bundle bâti sur `symfony/config`, `symfony/dependency-injection`, `symfony/console` et
`symfony/filesystem`. Twig, `phil/paged-media` et `phil/media-bundle` sont facultatifs : chaque intégration est
enregistrée quand son paquet est installé.

La conception est simple. `FontFiles` lit un fichier avec le décompresseur Brotli dont PHP dispose, `FontCatalogue`
détient les fichiers du répertoire, `FontFaces` écrit leurs règles et `Licences` les compare au registre. Trois
commandes et une fonction Twig les exposent, et `PdfFonts` et `MediaFonts` font le lien avec les autres paquets.

## État

| Partie | État |
|---|---|
| Configuration : répertoire, chemin public, `font-display`, registre des licences | Implémenté et testé |
| `FontFiles` : WOFF2 lu quand `ext-brotli` est chargée, message clair sinon | Implémenté et testé |
| `FontCatalogue` : fichiers par chemin, faces par famille, graisse et style, fichiers illisibles avec leur raison | Implémenté et testé |
| `FontFaces` : une règle par face, ses fichiers du plus petit au plus gros, URL sous le chemin public | Implémenté et testé |
| `Licences` et `phil:fonts:licences` : le registre conservé en JSON, les fichiers à vérifier, `--write` | Implémenté et testé |
| `phil:fonts:list`, `phil:fonts:css` et `fonts_css()` | Implémenté et testé |
| `PdfFonts` pour `phil/paged-media`, `MediaFonts` pour `phil/media-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'activation du bundle, et `ext-brotli`. |
| [Configuration](doc/fr/configuration.md) | Chaque option et les services qu'elle fournit. |
| [Le catalogue](doc/fr/catalogue.md) | `FontFiles`, `FontCatalogue` et `CatalogueEntry` : la lecture, WOFF2, la recherche d'une face. |
| [Règles `@font-face`](doc/fr/font-face.md) | `FontFaces`, `phil:fonts:css` et `fonts_css()`. |
| [Licences](doc/fr/licences.md) | `Licences` et `phil:fonts:licences` : le registre, les fichiers à vérifier, une construction qui s'arrête. |
| [Commandes](doc/fr/commands.md) | Les trois commandes, leurs options et leurs codes de sortie. |
| [Intégrations](doc/fr/integrations.md) | `PdfFonts` pour le générateur de PDF natif, `MediaFonts` pour la médiathèque. |
| [Points d'extension](doc/fr/extension-points.md) | Les services qu'une application utilise, remplace ou décore. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lance, en plus des exceptions de la bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et les tests d'une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/fonts-bundle
```

```yaml
# config/packages/phil_fonts.yaml
phil_fonts:
    directory: '%kernel.project_dir%/public/fonts'
    public_path: /fonts
```

```twig
<style>{{ fonts_css() }}</style>
```

```sh
bin/console phil:fonts:list
bin/console phil:fonts:licences          # fails while a file's licence is unchecked
bin/console phil:fonts:licences --write  # once checked
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/fonts` ; `ext-brotli` pour lire les fichiers WOFF2 ; Twig pour
`fonts_css()` ; `phil/paged-media` pour `PdfFonts` ; `phil/media-bundle` pour `MediaFonts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
