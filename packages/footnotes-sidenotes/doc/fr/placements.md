<!-- phil-dev source-sha256=6249efbf11efe706ab7073dc3131656bdc1b24eb2700faf2bdddfe43aa9a0451 -->
# Emplacements

`NotesRenderer::render()` prend le document, sa `Resolution`, un `Placement` et les options de la sortie.

```php
$renderer->render($document, $notes, Placement::EndOfSection, idPrefix: 'part-2', sectionLevel: 2, headingLevel: 3);
```

| Argument | Sens |
|---|---|
| `$placement` | Où vont les notes, `EndOfDocument` par défaut |
| `$idPrefix` | Le début de chaque identifiant ; tiré des blocs par défaut |
| `$sectionLevel` | Pour `EndOfSection`, le niveau le plus profond d'un `header` de premier niveau qui ouvre une section, 2 par défaut |
| `$headingLevel` | Le niveau du titre d'une région de notes, 2 par défaut |

Les blocs sont dessinés par le `HtmlRenderer` passé au constructeur, avec ses moteurs de rendu et ses filtres ; les
blocs de notes ne sont pas dessinés là où ils se trouvent. Un bloc qui lit tout le document, comme une table des
matières, le voit quand même en entier.

## Fin du document

Une seule région après le dernier bloc, avec les notes dans l'ordre de leur premier renvoi.

```html
<p>Text<sup class="isNoteReference"><a id="doc-ref-a-1" href="#doc-note-a" role="doc-noteref" aria-label="Note 1">1</a></sup></p>
<section id="doc-notes-0" class="isNotes" role="doc-endnotes" aria-labelledby="doc-notes-0-title">
  <h2 id="doc-notes-0-title" class="isNotesTitle">Notes</h2>
  <ol class="isNotesList" role="list">
    <li id="doc-note-a" class="isNote"><span class="isNoteMarker">1</span> The note
      <a class="isNoteBacklink" href="#doc-ref-a-1" role="doc-backlink" aria-label="Back to reference 1">↩︎</a></li>
  </ol>
</section>
```

Une note appelée deux fois a deux liens de retour, chacun suivi de son rang. Avec `Restart::Chapter` et une seule région
à la fin, le même numéro peut se trouver sur deux notes : associez un redémarrage à la fin de section, ou à un
emplacement dans le flux.

## Fin de chaque section

Une section commence à chaque `header` de premier niveau de niveau `$sectionLevel` ou supérieur. Une note va dans la
section de son premier renvoi ; une section dont le texte n'appelle aucune note n'a pas de région. Un titre imbriqué
dans un conteneur n'ouvre pas de section, puisqu'une région ne peut pas être placée à l'intérieur.

## Marge

La note est écrite là où elle est appelée la première fois, en contenu phrasé, valide dans un paragraphe. Toute la
sortie est enveloppée dans `<div class="hasSidenotes">`, qui garde la colonne libre sur les écrans larges.

```html
<span class="isSidenoteAnchor">
  <input type="checkbox" class="isSidenoteSwitch" id="doc-toggle-a" aria-label="Show note 1">
  <label class="isSidenoteToggle" for="doc-toggle-a"><sup class="isNoteReference">1</sup></label>
  <small id="doc-note-a" class="isSidenote" role="note"><span class="isNoteMarker">1</span> The note</small>
</span>
```

Un renvoi ultérieur à la même note est un lien vers elle. Voir [Style](styling.md).

## Pied de page

La note est écrite là où elle est appelée la première fois, sous la forme de l'élément que GCPM fait flotter au pied de
la page. Voir [Impression](print.md).

```html
<span id="doc-note-a" class="isPageNote" data-note-label="1">The note</span>
```

## Un renvoi dans un lien

HTML n'imbrique pas les liens. Un renvoi à l'intérieur d'un lien est écrit sans lien, et une note écrite sur place à cet
endroit (dans la marge ou au pied de la page) perd ses propres liens, mais garde leur texte.
