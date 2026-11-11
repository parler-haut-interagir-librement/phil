<!-- phil-dev source-sha256=a16b98e705ff81467a318d8ee66356062eab59b3bb38d2cc9f2c2800aafee486 -->
# Exceptions

Chaque exception implémente `Phil\PrintCompositor\Exception\PrintCompositorException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidLayout` | `InvalidArgumentException` | Une taille ou une résolution hors de sa plage, une boîte hors de sa zone ou à l'intérieur d'aucune zone, un pixel hors de son image, un canal de couleur ou une qualité WebP hors de sa plage, un nom vide, une zone que ne place pas une photo, une encre pour laquelle une tâche n'a aucun calque, un élément qui n'est ni une image ni un texte |
| `InvalidImage` | `InvalidArgumentException` | Des octets que GD ne sait pas décoder, un fichier image illisible, une image de plus de pixels que la limite |
| `UnsupportedFont` | `InvalidArgumentException` | Un fichier de police illisible ou qui n'est pas une police, un WOFF, un WOFF2 ou une collection |
| `MissingGlyphs` | `DomainException` | Un texte dont des caractères ne sont pas couverts par sa police |
| `TextDoesNotFit` | `DomainException` | Un texte qui n'entre pas dans sa boîte, même à sa taille minimale |
| `MissingCapability` | `RuntimeException` | Une construction de GD sans prise en charge de FreeType, de PNG ou de WebP, quand un appel en a besoin |

Chaque message nomme ce qui ne va pas et ce qui a été donné, par exemple
`The text "Rest in pieces" does not fit its 20 × 6 mm box, even at 14 pt.` ou
`The font "Example Serif" has no glyph for "€" (U+20AC) in the text "5 €".`
`MissingGlyphs` et `TextDoesNotFit` sont les deux que peut causer le texte d'un client : l'application les montre, dans
ses propres mots, à côté du champ où le texte a été saisi.
