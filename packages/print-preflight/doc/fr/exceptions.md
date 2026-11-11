<!-- phil-dev source-sha256=59ffb65bcd1a2ba46fa003bbf3f1f4f882c16df06a0bc45b1f1523be8c4bcd7b -->
# Exceptions

Chaque exception implémente `Phil\PrintPreflight\Exception\PrintPreflightException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidDocument` | `InvalidArgumentException` | Un format qui n'est pas positif, un rectangle de largeur ou de hauteur négative, une image sans pixel dans un sens ou dans un cadre sans taille, un fond perdu de page négatif, une couverture d'encre ou une épaisseur de filet négative, un nom d'espace colorimétrique PDF que la bibliothèque ne connaît pas |
| `InvalidProfile` | `InvalidArgumentException` | Un fond perdu ou une marge de sécurité négatifs, une résolution minimale qui n'est pas positive, une cible sous le minimum, un maximum sous la cible, un nombre minimal de pages sous un, un maximum sous le minimum, un multiple de pages sous un, une couverture d'encre maximale qui n'est pas positive, une épaisseur minimale de filet négative |

Chaque message nomme ce qui ne va pas et ce qui a été donné, par exemple `A size must be positive; 0 × 297 mm given.`
ou `The page count must be a multiple of at least 1; 0 given.`

Un document qui enfreint une règle d'un imprimeur n'est pas une exception : c'est un constat dans le rapport.
