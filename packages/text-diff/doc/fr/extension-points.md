<!-- phil-dev source-sha256=8c6306515854f818782c66e051e26ce005715fc5bd7f970b633438b0e56cc6c4 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service ni aucun port, et ses classes sont finales. Ce que
choisit une application passe par des arguments.

| Point | Comment |
|---|---|
| La borne du travail | Le `$maxEdits` de `TextDiff` : au-delà, ce qui reste est donné comme supprimé puis inséré en entier plutôt que calculé plus loin ; une paire de textes hostile coûte ainsi un temps borné |
| La façon de découper un texte | Le `Tokenizer` donné à `compare()`, ou `words()`, `lines()`, `paragraphs()` et `html()` |
| La façon de montrer les différences | `toHtml()` donne `<ins>` et `<del>` ; une autre présentation lit les `Operation` d'un `Diff`, ou ses `Change` avec leurs positions |
| La façon de rendre les blocs d'un document | Le `HtmlRenderer` de `phil/ujo` donné à `documentToHtml()` : les moteurs de rendu de blocs de l'application rendent ses propres blocs dans une différence |
| Intercepter les erreurs de la bibliothèque | Chaque exception implémente `Phil\TextDiff\Exception\TextDiffException` |

L'algorithme est celui de Myers et ne se remplace pas ; les jetons qu'il compare sont ceux que choisit une
application.
