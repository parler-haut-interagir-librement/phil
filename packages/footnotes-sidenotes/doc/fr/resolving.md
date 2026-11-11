<!-- phil-dev source-sha256=43dd498e7e1ff8a38ccb03629550659a12c153c70ac0acb8a97089bbd520c253 -->
# Résoudre les notes

`NoteResolver::resolve()` prend un document et donne une `Resolution`. Le document devrait d'abord être normalisé, comme
le font `Ujo::decode()` et `Ujo::normalize()` : la normalisation donne aux renvois leur forme canonique, la seule qui
est lue.

## Ce qui est recueilli

Une **note** est un bloc `footnote`, `{id, text}`, où qu'il se trouve : avant ses renvois, après eux, imbriqué dans une
colonne. Un **renvoi** est `<sup data-footnote="id">…</sup>` dans n'importe quel texte de n'importe quel bloc, blocs
imbriqués et éléments de liste compris. Le contenu de l'élément est remplacé à la sortie : un numéro qu'un lecteur de
Markdown y a conservé ne compte pour rien. Le texte des blocs `code`, `mermaid` et `math` n'est pas du HTML : ce qui y
ressemble à un renvoi est laissé tel quel.

## Ordre de lecture

Les blocs sont parcourus dans l'ordre du document, les valeurs d'un bloc dans l'ordre de ses données, chaque bloc
imbriqué là où il se trouve. Une note prend son numéro à son premier renvoi. Une note appelée plusieurs fois est une
seule note, avec un seul numéro et plusieurs valeurs `NoteReference`.

## Redémarrages

`NoteOptions::$restart` indique où la numérotation recommence :

| `Restart` | La numérotation recommence |
|---|---|
| `Document` | Jamais : une seule numérotation pour le document |
| `Chapter` | À chaque bloc `header` dont le niveau est `chapterLevel` ou supérieur ; 1 par défaut |
| `PageMarker` | À chaque bloc d'un type présent dans `pageMarkers` ; `['pageBreak']` par défaut |

Un chapitre ou un marqueur de page compte où qu'il se trouve, imbriqué ou non. `Note::$scope` donne la série à laquelle
une note appartient. Une note appelée de nouveau dans une série ultérieure garde le numéro de son premier renvoi.

## Numérotation

| Fabrique | Écrit |
|---|---|
| `Numbering::decimal()` | `1`, `2`, `3` |
| `Numbering::lowerRoman()` | `i`, `ii`, `iii`, en décimal au-delà de 3999 |
| `Numbering::upperRoman()` | `I`, `II`, `III`, en décimal au-delà de 3999 |
| `Numbering::symbols()` | `*`, `†`, `‡`, `§`, `‖`, `¶`, puis `**`, `††`, puis `***`, en décimal au-delà de trois tours |

`Numbering::symbols(['a', 'b'])` prend la suite de l'appelant. `NoteOptions::$numbering` s'applique par défaut ;
`$locales` donne la numérotation d'une locale ou d'une langue, et `resolve($document, 'fr-CA')` cherche `fr-CA`, puis
`fr`, puis la valeur par défaut.

## Problèmes

`Resolution::$problems` liste ce qui a été trouvé, chaque élément étant un `Problem` avec son `kind`, son `noteId`, le
`blockId` où il se trouve et `occurrences` :

| `ProblemKind` | Sens | À la sortie | `isError()` |
|---|---|---|---|
| `MissingNote` | Un renvoi ne nomme aucune note | Le renvoi est omis | Oui |
| `UnreferencedNote` | Une note qu'aucun renvoi ne nomme | La note est omise | Oui |
| `DuplicateId` | Une deuxième note avec le même identifiant | La première note est gardée | Oui |
| `ReferenceInNote` | Le texte d'une note contient un renvoi | Le renvoi est omis | Oui |
| `RepeatedReference` | Une note est appelée plusieurs fois | Une note, un lien de retour par renvoi | Non |

`Resolution::isClean()` indique qu'aucun problème ne fait perdre quoi que ce soit ; `problemsOf($kind)` lit un seul
genre. Une chaîne de publication peut refuser un document qui n'est pas propre ; la page d'un lecteur peut l'afficher
quand même.

## Coût

Le résolveur fait un seul parcours : le temps croît avec la taille du document, et un texte sans renvoi coûte une seule
recherche de sous-chaîne. Voir [Tests](testing.md#bancs-dessai).
