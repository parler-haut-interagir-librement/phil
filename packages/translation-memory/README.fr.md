<!-- phil-dev source-sha256=4eb9632754d497489f5f9ff9e59de47b7622311c8e0611c31ec08a5f1faec087 -->
# phil/translation-memory

**Traduire des documents segment par segment, réutiliser ce qui a déjà été traduit, et garder des termes cohérents.**

phil/translation-memory découpe un document en segments : un par champ texte de chaque bloc d'un document UJO, ou un
par phrase. Il donne à chaque segment une empreinte de sa source et un état, de `untranslated` à `reviewed`, et le rend
`stale` quand sa source change sous une traduction, sans perdre cette traduction. Une variante régionale comme `fr-CA`
hérite des cibles de sa langue parente et ne se traduit que là où elle diffère. Une mémoire de traduction propose ce qui
a déjà été traduit, exact ou proche, avec un score de 0 à 1. Un glossaire trouve les termes d'une source, formes
fléchies comprises, et vérifie que la cible utilise leurs traductions permises et aucune des traductions refusées. Des
contrôles de qualité signalent un point perdu, une balise manquante, une forme plurielle dont la langue a besoin, une
cible copiée de sa source ou une étiquette dessinée plus large que sa place, en pixels. Les segments partent vers un
outil de traduction en XLIFF 2.1 et reviennent en XLIFF 2.x ou 1.2 ; la mémoire voyage en TMX et le glossaire en TBX.
Les segments traduits sont remis dans le document.

Il s'adresse à toute application PHP qui publie en plus d'une langue ce que les gens écrivent en blocs : articles,
pages, règles, livres, aide. Les traducteurs travaillent segment par segment. La mémoire leur évite de traduire deux
fois la même phrase, le glossaire garde le vocabulaire d'un jeu ou d'un produit, et quand un auteur modifie la source,
seuls les segments qui ont changé demandent du travail de nouveau.

C'est une bibliothèque PHP simple dont les prérequis sont `ext-ctype`, `ext-dom`, `ext-libxml`,
`ext-mbstring`, `phil/ujo` (UJO (Universal JSON Output), le format de document à blocs), `phil/text-diff`, qui montre ce
qui a changé dans la source d'un segment périmé, et `phil/fonts`, qui lit la table des caractères de la police d'un
gabarit. La bibliothèque n'enregistre rien : l'application conserve les segments où elle veut et donne un stockage à la
mémoire, en mémoire ou le sien. `phil/translation-bundle` conserve les deux dans Doctrine et ajoute un écran de
traduction.

La conception est petite : un `Segment` est une valeur avec une clé, une source, une `Fingerprint`, un `SegmentState`
et une cible. `UjoSegmenter` découpe un document en `SourceSegment`, avec un `SentenceSplitter` pour le découpage par
phrase, et remet les segments en place. `SegmentSync` met les segments enregistrés à jour avec une nouvelle source.
`Variants` laisse une variante régionale hériter de son parent. `TranslationMemory` consulte un `MemoryStore` et calcule
le score avec `Similarity`. Un `Glossary` de `Term` trouve et vérifie, avec les formes d'un `Inflector`. Un `Checker`
exécute les `Check` de l'espace de noms `Check`. `XliffWriter`, `XliffReader` et `XliffMerge` échangent des segments,
`TmxWriter` et `TmxReader` la mémoire, `TbxWriter` et `TbxReader` le glossaire.

## État

| Partie | État |
|---|---|
| Segments : clé, source, empreinte, état, cible, et la source pour laquelle la cible a été écrite | Implémenté et testé |
| États `untranslated`, `suggested`, `translated`, `reviewed`, `stale`, et leurs transitions | Implémenté et testé |
| Documents UJO découpés par champ texte ou par phrase, blocs imbriqués compris, et segments traduits remis en place | Implémenté et testé |
| Phrases : fins, guillemets et balises fermants, abréviations, initiales, fins CJK | Implémenté et testé |
| Segments enregistrés mis à jour : conservés, déplacés, périmés, ajoutés, retirés | Implémenté et testé |
| Ce qui a changé dans la source d'un segment périmé, par `phil/text-diff` | Implémenté et testé |
| Mémoire de traduction : correspondances exactes, correspondances approximatives par distance de Levenshtein et trigrammes, un stockage en mémoire | Implémenté et testé |
| Variantes régionales héritant de leur parent, `fr-CA` et `en-CA` par défaut, dans les segments et la mémoire | Implémenté et testé |
| Glossaire : termes trouvés en mots entiers, traductions permises, conservées et refusées, vérifiées par décompte | Implémenté et testé |
| Formes fléchies des termes du glossaire, écrites ou produites par un fléchisseur ; pluriels réguliers de l'anglais et du français | Implémenté et testé |
| Contrôles de qualité : ponctuation, espaces, balises, pluriels ICU, identique à la source, chacun écarté par un indicateur | Implémenté et testé |
| Taille rendue en pixels à partir des chasses d'une police ; les caractères que dessine une face de `phil/fonts` | Implémenté et testé |
| XLIFF 2.1 écrit, avec les balises en codes en ligne ; XLIFF 2.0, 2.1, 2.2 et 1.2 lus ; fichiers fusionnés dans les segments | Implémenté et testé |
| TMX 1.4b écrit et lu ; TBX de 2019 écrit, TBX de 2019 et de 2008 lus | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Segments](doc/fr/segments.md) | `Segment`, les empreintes, les états et leurs transitions, les segments périmés et ce qui a changé. |
| [Segmentation](doc/fr/segmentation.md) | Le découpage d'un document UJO par champ ou par phrase, les clés, la remise en place des segments, la synchronisation. |
| [Mémoire de traduction](doc/fr/memory.md) | `TranslationMemory`, les correspondances exactes et approximatives, les scores, les stockages, l'apprentissage. |
| [Variantes régionales](doc/fr/variants.md) | `Variants`, les langues parentes, les cibles héritées, la mémoire d'une variante. |
| [Glossaire](doc/fr/glossary.md) | Les termes, les traductions permises, conservées et refusées, les formes fléchies, la recherche et la vérification. |
| [Contrôles de qualité](doc/fr/checks.md) | Les contrôles, les catégories de pluriel, les indicateurs, la taille rendue en pixels, l'écriture d'un contrôle. |
| [XLIFF](doc/fr/xliff.md) | L'exportation en XLIFF 2.1, l'importation de XLIFF 2.x et 1.2, la fusion, ce qui est refusé. |
| [TMX et TBX](doc/fr/tmx-and-tbx.md) | La mémoire en TMX, le glossaire en TBX, ce qui est lu et ce qui est refusé. |
| [Exceptions](doc/fr/exceptions.md) | `TranslationMemoryException` et ses exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui traduit. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/translation-memory
```

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentSync;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffWriter;
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);
$segmenter = new UjoSegmenter(Granularity::Sentence);

$result = SegmentSync::sync($storedSegments, $segmenter->segment($document));
$segments = $result->segments;                       // stale, moved, added and removed are listed too

$segments[0] = $segments[0]->translate('Bonjour le monde.');

$xliff = new XliffWriter()->write(XliffFile::fromSegments('page:42', LanguageTag::of('en'), LanguageTag::of('fr'), $segments));
$french = $segmenter->assemble($document, $segments);  // a UJO document, untranslated pieces kept in English
```

## Prérequis

PHP 8.5 ou plus récent, `ext-ctype`, `ext-dom`, `ext-libxml`, `ext-mbstring`, `phil/ujo`, `phil/text-diff` et `phil/fonts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
