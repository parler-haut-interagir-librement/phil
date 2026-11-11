<!-- phil-dev source-sha256=b54ad0b0166028c4f35f9899644ed0cd4696e840d51c1b0f23b8db1605842d29 -->
# Style

`resources/css/notes.css` met en forme du HTML sémantique avec des classes à la manière de phil-ux, `isX` et `hasX`,
dans la couche de cascade `phil.components`. Les règles propres à un site, écrites hors de toute couche, l'emportent
sans `!important`. Chaque valeur lit un jeton `--phil-*` et se rabat sur une valeur simple.

| Classe | Sur |
|---|---|
| `isNoteReference` | Le `sup` d'un renvoi |
| `isNotes`, `isNotesTitle`, `isNotesList` | La région des notes, son titre, sa liste |
| `isNote`, `isNoteMarker`, `isNoteBacklink` | Une note dans une liste, son libellé, un lien de retour |
| `hasSidenotes` | L'enveloppe d'une sortie avec des notes de marge |
| `isSidenoteAnchor`, `isSidenoteSwitch`, `isSidenoteToggle`, `isSidenote` | Le groupe d'une note de marge, sa case à cocher, son libellé, son texte |
| `isPageNote` | Une note au pied d'une page imprimée |

## La colonne de marge

À partir de 72 rem de large, `hasSidenotes` garde une colonne libre grâce à son remplissage de fin, et chaque note y
flotte, à côté de la ligne de son renvoi. La largeur et l'écart sont `--sidenote-column` et `--sidenote-gap`, réglés sur
`hasSidenotes`. Le flottement est `inline-end` : un document de droite à gauche a donc sa colonne à gauche.

## Écrans étroits

Sous 72 rem, chaque note se replie sous sa bascule : la marque dans le texte est le libellé d'une case à cocher, qui
ouvre la note sous la ligne, et le clavier peut l'atteindre. Un lien vers la note, depuis un renvoi ultérieur, l'ouvre
par `:target`.

## À l'impression

Sous `@media print`, la feuille ouvre aussi chaque note de marge dans le flux et masque les bascules et les liens de
retour, pour un lecteur qui imprime la page depuis un navigateur. Un livre passe plutôt par l'[emplacement en pied de
page](print.md).
