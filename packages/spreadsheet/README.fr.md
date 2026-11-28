<!-- phil-dev source-sha256=8e4cadb937908a8f2f7ff0cf96be9f63d44ad5b93128482cc1b4591892dfcd37 -->
# phil/spreadsheet

**Lit et écrit du CSV, du XLSX et de l'ODS en mémoire constante, avec des types de cellule explicites, une protection
contre l'injection de formules et des fichiers non fiables lus dans des limites.**

phil/spreadsheet est une bibliothèque PHP simple. Elle écrit un classeur — une feuille ou plusieurs, chacune avec ses
colonnes et ses lignes lues une seule fois depuis n'importe quel itérable — dans un flux, en CSV, en Office Open XML
(XLSX) ou en OpenDocument (ODS), et relit les lignes d'un tel fichier, une à une. Dans les deux sens, la mémoire
employée est celle d'une ligne et d'un fragment, quel que soit le nombre de lignes : l'exportation d'un million de
réponses s'exécute dans une tâche d'arrière-plan sans que la mémoire grandisse.

Chaque cellule a un type que le code énonce, jamais deviné d'après son texte : un texte, un nombre, un booléen, une
date, une date et heure, ou rien. Un code postal ou un code de produit écrit comme texte garde ses zéros de tête et
est relu comme texte. Un texte qu'un tableur exécuterait comme une formule — `=`, `+`, `-`, `@`, une tabulation ou un
retour chariot au début — reste un texte par défaut (OWASP, injection CSV).

Les lecteurs reçoivent des fichiers que personne n'a vérifiés : la taille du fichier, les entrées d'une archive, les
octets de chaque partie à mesure qu'ils sont décompressés, les lignes, les colonnes et les cellules sont bornés ; une
entrée hors de la racine de l'archive, une déclaration de type de document et une entité externe sont refusées ; rien
n'est jamais extrait sur le disque.

La bibliothèque écrit des tables de données : ni formules, ni graphiques, ni mise en forme riche, seulement, au plus,
une ligne d'en-tête en gras et figée et des largeurs de colonne.

## État

| Partie | État |
|---|---|
| `Cell`, `CellType`, `Column`, `Sheet`, `Workbook`, `WriteOptions`, `Headings`, `RowRange` : le modèle d'un classeur | Implémenté et testé |
| `CsvWriter`, `CsvReader`, `CsvDialect` : RFC 4180, marque d'ordre des octets, protection des formules, dialectes | Implémenté et testé |
| `XlsxWriter`, `XlsxReader` : chaînes en ligne, cellules typées, dates, textes partagés, systèmes de dates 1900 et 1904, schéma strict | Implémenté et testé |
| `OdsWriter`, `OdsReader` : cellules typées, paragraphes et espaces, lignes et cellules répétées | Implémenté et testé |
| `ReadLimits` et les défenses des lecteurs : bombe ZIP, entrées hors de la racine, XXE, tailles comptées à la lecture | Implémenté et testé |
| `Spreadsheets` et `Format` : un écrivain ou un lecteur selon le format, l'extension ou le type de média | Implémenté et testé |
| Les cibles de fuzzing des trois lecteurs et les bancs d'essai de 100 000 lignes | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, et une première exportation et importation. |
| [Écriture](doc/fr/writing.md) | Le classeur, les cellules et leurs types, les options, la sortie et les limites de chaque format. |
| [Lecture](doc/fr/reading.md) | Les lignes lues, les types de chaque format, les feuilles et les limites. |
| [Sécurité](doc/fr/security.md) | La protection des formules, et comment les lecteurs traitent des fichiers que personne n'a vérifiés. |
| [Points d'extension](doc/fr/extension-points.md) | Les contrats de l'écrivain et du lecteur, et quand l'API est stable. |
| [Tests](doc/fr/testing.md) | Les tests, les cibles de fuzzing et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/spreadsheet
```

```php
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Workbook;

$rows = (function () use ($answers): iterable {
    foreach ($answers as $answer) {
        yield [$answer->postalCode, $answer->score, $answer->submittedAt];
    }
})();

$spreadsheets = new Spreadsheets();
$spreadsheets->writeFile(
    Workbook::single('Answers', $rows, [new Column('postal_code', 'Postal code', 12), new Column('score', 'Score'), new Column('at', 'Submitted')]),
    '/var/exports/answers.xlsx',
);

foreach ($spreadsheets->reader('answers.xlsx')->rows('/var/exports/answers.xlsx') as $row) {
    [$postalCode, $score, $submittedAt] = $row->values();
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-libxml`, `ext-mbstring`, `ext-xmlreader` et `ext-zip`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
