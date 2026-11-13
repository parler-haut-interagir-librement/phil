<!-- phil-dev source-sha256=d98c92beb325e7cd63ad893655fcebbf5a7f9d2cfbd5c80e0d8e75491eb6d7f8 -->
# Points d'extension

La bibliothèque est du simple PHP : elle ne déclare ni service ni port, et ses classes sont finales. Ce qu'une
application choisit passe par le livre qu'elle décrit et par les arguments du rédacteur.

| Point | Comment |
|---|---|
| Les adresses des chapitres | La fonction anonyme `$urls` de `Book` : chaque adresse trouvée dans les chapitres est associée avant que le rédacteur la lise, soit les adresses de médias de l'application vers les chemins des images qu'elle a ajoutées, une URL absolue, ou `null` pour la retirer |
| Les mots de la navigation | `NavigationLabels`, donné au livre : les titres de la table des matières, des repères et de la liste de pages dans la langue du livre |
| La table des matières | Construite à partir des titres jusqu'à `$tocDepth`, ou donnée sous forme de liste de `TocEntry` |
| La mise en page | `Rendition`, donnée au livre, et le `layout`, le `viewport` et le `pageSpread` de chaque chapitre |
| La narration | Un `MediaOverlay` par chapitre, et les narrateurs et les classes de `Narration`, donnée au livre |
| Les solutions de repli de l'audio et de la vidéo | L'argument `fallback` d'`Asset::audio()` et d'`Asset::video()` |
| Les anciens systèmes de lecture | `Book::withNcx()` ajoute la table des matières EPUB 2 |
| L'endroit où un fragment de HTML est coupé en chapitres | `HtmlSplitter::split()` à un niveau de titre, ou des chapitres construits un par un |
| Le rendu des blocs UJO | `UjoChapters` prend le `Normalizer` et le `HtmlRenderer` de `phil/ujo` : un moteur de rendu doté des moteurs de rendu de blocs de l'application ajoute ses propres blocs au livre ; `pageBreak` est toujours rendu comme un marqueur de page de la liste de pages |
| L'endroit où vont les fichiers temporaires | Le `$temporaryDirectory` d'`EpubWriter` |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Epub\Exception\EpubException` |

Le XHTML qu'émet le rédacteur est toujours purifié et bien formé : un moteur de rendu de l'application peut écrire
n'importe quel HTML, le livre ne reçoit que ce qui passe.
