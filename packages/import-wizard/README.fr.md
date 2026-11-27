<!-- phil-dev source-sha256=723c9ee9b43c62770e291f003973d171ae218ce7fd7c5d422bc4879e4cefa3da -->
# phil/import-wizard

**Importez des fichiers CSV sans surprise : un essai à blanc donne pour chaque ligne l'aperçu d'une création, d'une mise à jour ou d'un refus, les correspondances se rejouent et les lignes refusées reviennent à corriger.**

phil/import-wizard est une bibliothèque PHP simple. Une application décrit chaque type importable par un
**`ImportProfile`** : ses colonnes et leurs types, les vérifications de chaque cellule, les règles qui portent sur une
ligne entière, la **clé naturelle** qui distingue une mise à jour d'une création — une adresse courriel, un code, une
personne et un cours —, et les références à d'autres enregistrements. Le profil se sérialise en JSON et se relit à
l'identique, avec la **correspondance** des en-têtes du dernier fichier, afin que le fichier suivant de même forme soit
lu de la même façon.

`CsvRowSource` lit le fichier dans ses limites — UTF-8 ou un encodage Windows déclaré, délimiteur détecté, cellules
entre guillemets sur plusieurs lignes — et garde la **ligne physique** de chaque ligne, celle qu'affiche un tableur. La
correspondance est devinée à partir des en-têtes, en anglais ou en français, avec ou sans accents, puis complétée par
ce qu'ajuste la personne qui importe. **`ImportPlanner`** fait ensuite l'**essai à blanc** : ligne par ligne, créer,
mettre à jour ou refuser, avec la raison de chaque cellule refusée, les doublons d'une clé refusés, et les références
résolues vers un enregistrement déjà connu ou vers une ligne du même fichier, même plus bas — un arbre d'unités s'écrit
en commençant par les parents, quel que soit l'ordre du fichier. Rien n'est écrit avant que la personne ait vu cet
aperçu.

**`Importer`** écrit le plan par une **`ImportTarget`**, le port qu'implémente l'application ou le bundle qui possède
les enregistrements : la bibliothèque n'écrit jamais dans une base de données. Un lot ne contient jamais une ligne et
celle à laquelle elle se réfère, une ligne dont la cible a refusé le parent est refusée à son tour, et un plan refait
depuis l'aperçu n'est écrit que si son empreinte est la même. **`RejectedRowsFile`** rend les lignes refusées sous
forme d'un fichier CSV, chacune avec sa ligne et ses erreurs, à corriger puis à importer de nouveau ;
**`TemplateFile`** donne le gabarit d'un type, réduit à ses en-têtes.

`LmsProfiles` fournit les trois fichiers CSV d'une plateforme d'apprentissage : les personnes, leurs inscriptions à des
cours et l'arbre des unités organisationnelles. Les questions des quiz et des sondages suivent les conventions communes
des fichiers de questions, avec les profils des paquets qui les possèdent.

## État

| Partie | État |
|---|---|
| `ImportProfile`, `Column`, `ColumnType`, `Reference`, `ColumnMapping` : profils sérialisés et rejoués | Implémenté et testé |
| `CsvRowSource`, `CsvReader`, `CsvDialect`, `SourceLimits` : CSV lu dans ses limites, lignes physiques conservées | Implémenté et testé |
| `ImportPlanner`, `ImportPlan` : l'essai à blanc, les clés naturelles, les références dans le fichier et hors de lui | Implémenté et testé |
| `Importer`, `Batch`, `ImportTarget` : le plan écrit par lots à travers la cible, empreinte vérifiée | Implémenté et testé |
| `RejectedRowsFile`, `TemplateFile`, `CsvWriter` : les fichiers à corriger et les gabarits, formules protégées | Implémenté et testé |
| `LmsProfiles` : personnes, inscriptions, unités organisationnelles | Implémenté et testé |
| `DateOrder` et le port `RowRule` | Implémenté et testé |
| Les conventions des fichiers de questions des quiz et des sondages, dont les profils sont dans leurs paquets | Documenté |
| Lecture de XLSX et d'ODS, de GIFT et de QTI | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et une première importation du début à la fin. |
| [Profils](doc/fr/profiles.md) | Colonnes, types, vérifications, clés naturelles, références, règles, correspondance, sérialisation. |
| [Formats](doc/fr/formats.md) | Le CSV lu et écrit, et les trois formats d'une plateforme d'apprentissage. |
| [Fichiers de questions](doc/fr/question-files.md) | Les fichiers CSV des questions des quiz et des sondages, et les conventions qu'ils partagent. |
| [L'essai à blanc](doc/fr/dry-run.md) | Le plan, l'aperçu, les raisons, son écriture et les fichiers de lignes refusées. |
| [Points d'extension](doc/fr/extension-points.md) | La cible, la recherche, les sources et les règles qu'implémente une application. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/import-wizard
```

```php
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Output\RejectedRowsFile;
use Phil\ImportWizard\Plan\ImportPlanner;

$profile = LmsProfiles::people();
$plan = new ImportPlanner()->plan(CsvRowSource::fromFile($uploadedPath), $profile, $peopleTarget);

// Show $plan->preview() — create, update or refuse, line by line — and keep $plan->fingerprint with the confirmation.

$result = new Importer()->commit($plan, $peopleTarget, $confirmedFingerprint);
$toCorrect = RejectedRowsFile::csv($plan, $result->rejections);
$profileJson = $profile->withMapping($plan->mapping)->toJson(); // replayed on the next file
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-filter` et `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
