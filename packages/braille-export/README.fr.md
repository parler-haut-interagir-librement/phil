<!-- phil-dev source-sha256=a2656fd48df2562c05f217694141e83eb43a18a54d1329f2a2188bc92bd392fa -->
# phil/braille-export

**Un document mis en page en pages braille et écrit en BRF, en PEF ou en braille Unicode, en braille abrégé ou intégral.**

phil/braille-export est une bibliothèque PHP ordinaire. Elle prend un document structuré simple — titres, paragraphes,
listes, tableaux, sauts de page et repères de page imprimée — ou un document UJO (Universal JSON Output), traduit son
texte en cellules braille, répartit les cellules en pages braille et écrit les pages dans le format que lit une
imprimante braille, un bloc-notes braille ou un écran.

- **La traduction** passe par le port `Translator`, un lot par document. `UncontractedTranslator` écrit en PHP le
  français intégral (Code braille français uniformisé) et l'anglais intégral (Unified English Braille), sans programme
  à installer. Le braille abrégé, et toutes les autres langues, viennent d'un traducteur à tables complètes, comme
  `phil/braille-export-liblouis` ; `ChainTranslator` place derrière lui le traducteur PHP pour le cas où il manque.
- **La mise en page** suit la page braille : 40 cellules sur 25 lignes par défaut, paragraphes commençant à la
  cellule 3, titres centrés ou aux cellules 5 et 7 et gardés avec la ligne qui les suit, listes mises en retrait selon
  le niveau, tableaux linéarisés rangée par rangée, numéro de page braille à la fin de la dernière ligne, page imprimée
  à la fin de la première ligne et ligne de points 3-6 là où commence une page imprimée. Un mot trop long pour une
  ligne est coupé après son propre trait d'union quand il en a un, pour qu'aucun second trait d'union ne soit ajouté.
- **Les formats** : BRF, le fichier braille ASCII nord-américain qu'impriment les imprimantes braille ; PEF 2008-1, le
  XML du DAISY Consortium qui garde les volumes, les sections et les pages ; le braille Unicode, pour un aperçu à
  l'écran.

C'est un paquet à part, distinct des gabarits de médias paginés : une édition braille se met en page en cellules, non
en points, et n'a besoin d'aucun moteur d'impression.

## État

| Partie | État |
|---|---|
| Le modèle de document, et les documents UJO lus dans ce modèle | Implémenté et testé |
| `UncontractedTranslator` : français et anglais intégraux | Implémenté et testé |
| `ChainTranslator` et le port `Translator` | Implémenté et testé |
| `BrailleExporter` et `PageFormat` : la mise en page des pages braille | Implémenté, testé et fuzzé |
| `BrfWriter`, `PefWriter`, `UnicodeWriter` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le traducteur à choisir. |
| [Documents](doc/fr/documents.md) | Les blocs d'un document, et un document UJO lu dans ces blocs. |
| [Traduction](doc/fr/translation.md) | Le port `Translator`, le français et l'anglais intégraux, le braille abrégé, la chaîne. |
| [Mise en page](doc/fr/layout.md) | Le format de page et la mise en page de chaque bloc : retraits, titres, listes, tableaux, numéros de page, coupure des mots. |
| [Formats de sortie](doc/fr/output-formats.md) | BRF, PEF et braille Unicode. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le paquet, et quand. |
| [Sécurité](doc/fr/security.md) | Ce qu'un texte peut et ne peut pas faire, et les bornes d'un export. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/braille-export
```

```php
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Output\PefWriter;
use Phil\BrailleExport\Translation\UncontractedTranslator;

$document = new Document([
    new Heading('Chapitre premier'),
    new Paragraph("Lorsque j'avais six ans, j'ai vu une magnifique image."),
], 'fr', 'Le livre');

$book = new BrailleExporter(new UncontractedTranslator())->layout($document);

file_put_contents('book.brf', new BrfWriter()->write($book));
file_put_contents('book.pef', new PefWriter()->write($book));
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` ; `phil/ujo` 0.1. Le braille abrégé exige un traducteur à tables
complètes, comme `phil/braille-export-liblouis`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
