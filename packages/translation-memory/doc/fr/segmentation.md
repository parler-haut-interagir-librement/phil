<!-- phil-dev source-sha256=1ac3b745c950f8e2f6d21e428c5615a29fa151a68a39f5c890a4a0fa05256052 -->
# Segmentation

`UjoSegmenter` découpe un document UJO (un `Phil\Ujo\Document`) en `SourceSegment`, et remet en place les segments
traduits.

## Ce qui est traduit

Chaque bloc, y compris les blocs imbriqués, dans l'ordre de lecture : un bloc vient avant les blocs qu'il contient.
Dans chaque bloc, chaque champ texte dont le nom (ou, dans une liste, le nom de la liste) fait partie de
`TranslatableFields::NAMES` : `text`, `caption`, `title`, `message`, `content`, `description`, `name`, `term`,
`subtitle`, `alt`, `label`, `summary`. Les blocs des types de `TranslatableFields::UNTRANSLATED_TYPES` (`code`,
`mermaid`, `math`, `raw`) sont laissés tels quels. Ces deux listes sont les valeurs par défaut de
`TranslatableFields`, qui en accepte d'autres :

```php
new UjoSegmenter(fields: new TranslatableFields([...TranslatableFields::NAMES, 'quote'], ['code', 'raw']));
```

Un champ sans mots (vide, des espaces, des balises seulement) ne produit aucun segment. Les clés de données qui ne
sont pas faites de 1 à 64 lettres, chiffres ou `_`, et les valeurs imbriquées sur plus de 16 niveaux, ne sont pas
lues.

## Clés

Une clé joint l'identifiant du bloc et le chemin du champ dans les données du bloc :

| Champ | Clé |
|---|---|
| Le texte d'un paragraphe | `b3/text` |
| Le premier élément d'une liste | `b7/items.0.content` |
| Un élément imbriqué | `b7/items.0.items.1.content` |
| Une cellule de tableau | `b9/content.1.2` |
| La deuxième phrase d'un paragraphe, par phrase | `b3/text#2` |

Les clés restent les mêmes tant que les blocs gardent leurs identifiants, ce que UJO assure d'un enregistrement à
l'autre.

## Par phrase

`Granularity::Sentence` découpe chaque champ avec un `SentenceSplitter`. Une phrase se termine à `.`, `!`, `?` ou `…`,
avec les guillemets, les crochets et les balises fermants qui suivent (y compris un `»` français après son espace),
quand une espace suit et que le mot suivant ne commence pas par une minuscule. À `。`, `！` ou `？`, elle se termine même
sans espace. Un point après une abréviation connue (`Mr.`, `Dr.`, `e.g.`, `M.`, `Mme.`, `c.-à-d.` et les autres de
`SentenceSplitter::ABBREVIATIONS`) ou après une lettre seule, une initiale, ne termine rien. Les balises ne sont jamais
coupées, et les phrases jointes redonnent exactement le texte. Le découpeur accepte ses propres abréviations :

```php
new UjoSegmenter(Granularity::Sentence, splitter: new SentenceSplitter([...SentenceSplitter::ABBREVIATIONS, 'approx']));
```

## Remettre les segments en place

```php
$translated = $segmenter->assemble($document, $segments);
$preview = $segmenter->assemble($document, $segments, [SegmentState::Translated, SegmentState::Reviewed, SegmentState::Suggested]);
```

Chaque morceau est remplacé par la cible de son segment quand le segment est dans l'un des états acceptés (traduit et
révisé par défaut) et qu'il a été fait à partir de ce texte même : même empreinte. Tout autre morceau reste dans la
langue source, si bien qu'un document est toujours complet. Les espaces autour de chaque phrase sont conservées. Le
résultat est un nouveau `Document` ; rien d'autre n'y change. Une cible est du HTML en ligne écrit par un traducteur ou
apporté par un fichier : produisez le rendu du document par `phil/ujo`, dont l'assainisseur ne garde que les balises
en ligne qu'il permet.

## Synchroniser

Quand la source change, découpez-la de nouveau et mettez à jour les segments enregistrés :

```php
$result = SegmentSync::sync($stored, $segmenter->segment($document));
```

Chaque morceau de la source est associé, dans cet ordre, à : le segment enregistré de même clé et de même empreinte,
conservé ; un segment enregistré de même empreinte sous une autre clé qui a une cible (une phrase repoussée par une
nouvelle, un bloc déplacé dans une colonne), porté vers la nouvelle clé, en préférant un segment du même champ ; le
segment enregistré de même clé, dont la source a changé, périmé s'il a une cible, non traduit sinon ; ou rien, ce qui
donne un nouveau segment non traduit.

| `SyncResult` | Contient |
|---|---|
| `segments` | Chaque segment de la source, dans son ordre |
| `added` | Les clés des nouveaux segments |
| `stale` | Les clés des segments dont la source a changé sous une cible |
| `moved` | Nouvelle clé ⇒ ancienne clé, pour les segments portés vers une autre clé |
| `removed` | Les segments enregistrés qui ne sont plus dans la source |
| `hasChanges()` | Si l'une des quatre listes n'est pas vide |
