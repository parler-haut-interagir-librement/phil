<!-- phil-dev source-sha256=76689f76a1ded45bd6c7ffd778972afcdaf1981481370a2efd33b2f084eb6bbc -->
# phil/data-table-export

**Des lignes écrites en fichiers CSV et XLSX à mesure qu'elles sont lues, en mémoire bornée.**

phil/data-table-export est une bibliothèque PHP. Elle écrit une feuille — un titre, les en-têtes et des lignes lues une
seule fois dans n'importe quel itérable — dans un flux, en CSV ou en classeur XLSX. Elle ne sait rien de l'origine des
lignes : un tableau de données, un rapport ou une commande de console lui remet un générateur et un flux.

Le CSV suit la RFC 4180, commence par une marque d'ordre des octets UTF-8 et garde comme du texte simple tout texte
qu'un tableur exécuterait comme une formule. Le XLSX est écrit avec ext-zip et du XML ; ses textes sont des chaînes en
ligne qui ne s'exécutent jamais, ses nombres, booléens et dates restent ce qu'ils sont, ses en-têtes sont en gras et
figés. Les deux écrivent les lignes à mesure qu'elles arrivent : la mémoire reste celle d'une ligne pour le CSV et d'un
morceau pour le XLSX, quel que soit le nombre de lignes.

## État

| Partie | État |
|---|---|
| La feuille, les formats et le registre des écrivains | Implémenté et testé |
| Le CSV selon la RFC 4180, la marque d'ordre des octets, la protection contre l'injection de formules | Implémenté et testé |
| Le XLSX avec ext-zip, chaînes en ligne, nombres, booléens et dates typés, en-têtes figés, limites du format | Implémenté et testé |
| La cible de fuzzing du texte d'une cellule et les bancs d'essai des deux formats | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Écrire une feuille](doc/fr/sheets.md) | La feuille, ses cellules, les formats, un téléchargement depuis un contrôleur. |
| [CSV](doc/fr/csv.md) | Le dialecte, la marque d'ordre des octets et la protection contre les formules. |
| [XLSX](doc/fr/xlsx.md) | Le classeur, les sortes de cellules, les limites et les fichiers temporaires. |
| [Points d'extension](doc/fr/extension-points.md) | Un écrivain d'un autre format, et quand l'API est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/data-table-export
```

```php
use Phil\DataTableExport\ExportWriters;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;

$rows = (function () use ($books): iterable {
    foreach ($books as $book) {
        yield [$book->title, $book->pages, $book->publishedOn];
    }
})();

$stream = fopen('php://output', 'w');
ExportWriters::defaults()->write(Format::Xlsx, new Sheet('Books', ['Title', 'Pages', 'Published'], $rows), $stream);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec les extensions mbstring et zip.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
