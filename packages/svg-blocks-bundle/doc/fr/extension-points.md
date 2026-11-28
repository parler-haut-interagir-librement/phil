<!-- phil-dev source-sha256=aa7d34c333559c21027afd5c7d7473c080a581758c21f43b3b520750e0bc4770 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Lancer la passe en arrière-plan | Implémentez `Application\Contract\MarkupDocuments` et nommez-le dans `mermaid.documents` |
| Dessiner un autre type, ou en dessiner un autrement | Redéfinissez le service `Phil\SvgBlocks\BlockMarkup` avec vos propres moteurs de rendu |
| Appeler un autre service de diagrammes | Définissez un `Phil\SvgBlocks\Contract\MermaidService` et donnez-le à un `MermaidRenderer` |
| Laisser tels quels les documents d'un champ | `svg_blocks: false` sur le champ |
| Afficher l'aperçu dans votre propre balisage | Écoutez `phil--svg-blocks-bundle--preview:rendered` |
