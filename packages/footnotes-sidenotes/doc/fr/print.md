<!-- phil-dev source-sha256=a73a51ede4ea78d40e70a9cc8a2e57ffefe25ab072184f85beda4919042b5ab6 -->
# Impression

`Placement::PageFoot` et `resources/css/notes-print.css` suivent CSS Generated Content for Paged Media, pour un moteur
qui place les notes en bas de page, comme WeasyPrint.

| Partie | Dans la feuille |
|---|---|
| La note | `.isPageNote { float: footnote }`, sur l'élément écrit à la place de son renvoi |
| L'appel dans le texte | `::footnote-call`, dont le contenu est l'attribut `data-note-label` |
| La marque dans la note | `::footnote-marker`, le même attribut suivi d'un point |
| La zone | `@page { @footnote { … } }`, un filet mince au-dessus des notes |

L'attribut `data-note-label` porte le libellé du résolveur : les redémarrages, les chiffres romains et les symboles
s'impriment donc tels qu'ils ont été résolus, sans le compte propre du moteur.

Un renvoi ultérieur à une note déjà écrite est un lien qui porte son libellé, un `sup` de classe `isNoteReference` : une
note se trouve sur sa page, et un renvoi sur une autre page la nomme quand même.

## Espaces

Le texte d'une note est écrit sur une seule ligne : chaque suite d'espaces ASCII devient une seule espace, et il n'en
reste aucune aux extrémités. Un moteur d'impression peut transformer un saut de ligne dans la source d'un élément
flottant en saut forcé, ce qui coupe la note après ses premiers mots ; le même texte sur une ligne se lit comme un
paragraphe. Les espaces insécables, que la typographie place exprès, restent. Un `<br>` dans une note reste un saut de
ligne.

## Recommencer à chaque page réelle

La numérotation que connaît le résolveur est celle du document, de ses chapitres et de ses blocs `pageBreak` explicites.
Seul le moteur d'impression sait où une page se termine dans le flux. Pour des notes numérotées par page réelle,
laissez-lui le compte avec `resources/css/notes-print-per-page.css`, `Stylesheet::PrintPerPage`, après la feuille
d'impression :

```css
@page { counter-reset: footnote; }
.isPageNote::footnote-call { content: counter(footnote); }
.isPageNote::footnote-marker { content: counter(footnote) ". "; }
```

Un moteur qui suit CSS GCPM recommence le compte à chaque page. WeasyPrint 70 compte les notes avant de mettre les pages
en page et ignore cette remise à zéro : ses numéros continuent d'une page à l'autre.
