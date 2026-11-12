<!-- phil-dev source-sha256=3781797a96eda14e209b47d663d89f1ddfbd8504d4289b94145d419071aadfbf -->
# Traduire des documents

`Phil\TranslationBundle\Application\Translations` traduit les documents de l'application segment par segment, dans les
stockages du bundle. Un document est nommé par une référence de 1 à 255 octets choisie par l'application
(`article:42`) et traduit dans autant de langues cibles que voulu, nommées par des étiquettes BCP 47.

Le bundle ne traduit que des documents UJO (Universal JSON Output). Les catalogues de messages de Symfony (les fichiers
`messages+intl-icu` de l'interface d'une application) restent aux outils faits pour eux : le composant de traduction de
Symfony, ses extracteurs et ses fournisseurs de traduction.

## Préparer

```php
$result = $translations->prepare('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
```

La source est découpée en segments selon la granularité configurée, et les segments enregistrés du document dans
cette langue cible sont mis à jour : conservés, déplacés avec leur traduction, rendus périmés, ajoutés ou retirés. Le
`SyncResult` liste chacun d'eux. Quand `prefill` est actif, chaque nouveau segment non traduit reçoit comme
proposition la correspondance exacte de la mémoire, s'il y en a une, comme `suggested`. Les lignes sont écrites dans
l'ordre de la source.

Préparez un document avant de le changer ou de l'importer (l'écran le fait à chaque `POST`), et après l'enregistrement
de sa source, pour que les segments périmés apparaissent tout de suite.

```php
$result = $translations->preview('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
$xml = $translations->exportPreview('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
```

`preview()` donne le `SyncResult` que donnerait une préparation, et `exportPreview()` le fichier XLIFF 2.1 de ces
segments, sans écrire de ligne ni toucher aux lignes enregistrées : c'est ce que montre une requête qui ne fait que
lire. La page et l'exportation de l'écran sont de telles requêtes `GET`.

## Agir sur un segment

| Méthode | Résultat |
|---|---|
| `translate($document, $language, $key, $target)` | `translated`, la cible écrite pour la source courante |
| `suggest($document, $language, $key, $target)` | `suggested` |
| `review($document, $language, $key)` | `reviewed` ; refusé sur un segment non traduit ou périmé |
| `confirm($document, $language, $key)` | `translated` de nouveau, pour un segment périmé dont la cible tient toujours |
| `clear($document, $language, $key)` | `untranslated` |

Chacune retourne le nouveau `Segment`, lance `NotFound` pour une clé que le document n'a pas, et laisse passer
`InvalidSegment` pour un changement que le segment ne peut pas accepter. Une cible qui devient utilisable (traduite,
révisée ou confirmée) est apprise par la mémoire, avec le document pour origine. Chacune écrit aussi qui a écrit et qui
a révisé la cible, comme l'énumère [Commentaires et crédits](collaboration.md#qui-a-fait-quoi), et efface la marque de
sortie de machine quand une personne écrit une nouvelle cible. `records($document, $language)` donne les lignes par clé
de segment, avec `translatedBy()`, `reviewedBy()`, `isMachine()` et `flags()`.

`segments()` donne les segments d'un document dans l'ordre, `segment()` l'un d'eux, et `progress()` un `Progress` :
le nombre de segments de chaque état, les segments utilisables et leur part en pourcentage entier.

## Correspondances, termes et problèmes

`suggestions()` donne ce que la mémoire propose pour un segment, `terms()` les termes du glossaire présents dans sa
source, `issues()` les problèmes de glossaire de sa cible, aucun sans cible. Pour plusieurs segments à la fois,
`matches()` prend un segment en main, et `glossary()` donne le glossaire de la paire de langues, avec lequel appeler
`find()` et `check()`.

## Contrôles de qualité

`check($segment, $sourceLanguage, $targetLanguage, $flags)` exécute les contrôles de qualité de
`phil/translation-memory` sur un segment en main (ponctuation, espaces, balises, pluriels ICU et cibles identiques à
leur source) et donne leurs `CheckIssue`, aucun sans cible. Les indicateurs d'un segment sont conservés avec sa ligne :

```php
$translations->dismiss('article:42', LanguageTag::of('fr'), 'b1/text', 'same');                 // adds ignore-same
$translations->flag('article:42', LanguageTag::of('fr'), 'b1/text', Flags::parse('max-size:240'));
```

`dismiss()` ajoute l'indicateur `ignore-<check>`, et `flag()` remplace les indicateurs ; écrits, ils font au plus
1024 octets, sinon `InvalidRecord` est lancée. Un nom qui n'est pas un indicateur laisse passer l'`InvalidConfiguration`
de la bibliothèque. Avec un `Checker` comme dernier argument, `Translations` exécute ces contrôles plutôt que ceux par
défaut de la bibliothèque, avec un `MaxSize` de la police de l'application, par exemple.

## XLIFF

```php
$xml = $translations->export('article:42', LanguageTag::of('fr'));
$report = $translations->import('article:42', LanguageTag::of('fr'), $uploadedXml, canReview: $policy->canReview('article:42', LanguageTag::of('fr')));
```

`export()` écrit les segments d'un document préparé en XLIFF 2.1. `import()` lit du XLIFF 2.x ou 1.2, vérifie que le
fichier est dans les langues du document, le fusionne, écrit les segments qu'il a changés et apprend ceux qu'il a
rendus utilisables. Le `MergeResult` liste les clés appliquées et les unités ignorées, avec leur raison. Les deux
lancent `NotFound` pour un document non préparé dans la langue ; `import()` laisse passer `InvalidXliff` et
`InvalidLanguage` de la bibliothèque.

`import()` fusionne une unité marquée `final` ou `signed-off` comme révisée seulement quand `canReview` vaut `true` ;
par défaut, et pour qui ne peut pas réviser, il la fusionne comme traduite, sa cible gardée, et le `MergeResult` le
dit. L'écran passe `canReview()` de la politique.

## Traduction automatique

`machineTranslate()` propose les cibles du traducteur automatique configuré aux segments non traduits ; voir
[Traduction automatique](machine-translation.md).

## Crédits

`credits()` donne les personnes remerciées pour la traduction d'un document ; voir
[Commentaires et crédits](collaboration.md#credit-public).

## Le document traduit

```php
$french = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'));
$preview = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'), [SegmentState::Translated, SegmentState::Reviewed, SegmentState::Suggested]);
```

Chaque morceau de la source est remplacé par la cible de son segment quand le segment est dans un état accepté
(traduit ou révisé par défaut) et qu'il a été fait à partir de ce texte même. Tout autre morceau reste dans la langue
source. Pour une variante régionale, `translated()` lit selon sa chaîne (`fr-CA`, puis `fr`, puis la source) et `raw()`
ne lit que les segments propres à la variante ; voir [Variantes régionales](variants.md). Le résultat est un document
UJO dont on produit le rendu par `phil/ujo` ou `phil/ujo-bundle`, dont l'assainisseur ne garde que les balises en ligne
qu'il permet.
