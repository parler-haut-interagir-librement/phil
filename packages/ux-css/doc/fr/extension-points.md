<!-- phil-dev source-sha256=c9053ef3b7ad25fbdb3e0cd28c96f54b8507f190d2281a6d1968904a13498f2f -->
# Points d'extension

| Point | Comment |
|---|---|
| Un jeton | Le définir dans le `:root` du site, hors de toute couche : il l'emporte sur les couches des jetons |
| Une échelle entière | Définir son multiplicateur : `--phil-scale`, `--phil-scale-space`, `--phil-scale-text`, `--phil-scale-radius` |
| Une teinte | Définir ses tons, chacun à la clarté que dit son numéro, puis passer la règle de contraste |
| Un rôle | Donner à `--phil-color-<role>-<part>` un autre `light-dark()` de deux tons |
| Le schéma | `data-theme` sur la racine ou sur n'importe quel élément |
| L'écart ou la mesure d'un élément | La propriété personnalisée de la classe de mise en page : `--phil-stack-gap`, `--phil-center-measure`… |
| Une tonalité à soi | Une classe qui définit les six `--phil-tone-*`, dans une couche après `phil.components` ou hors de toute couche |
| Un bouton | `--phil-button-fill`, `--phil-button-fill-hover`, `--phil-button-fill-active`, `--phil-button-on`, `--phil-button-border` |
| La taille des contrôles | `--phil-control-sm`, `-md`, `-lg` ; ou `--phil-control-size` sur un élément |
| La largeur d'un dialogue ou d'un panneau latéral | `--phil-dialog-size` sur l'élément |
| Le carrousel | `--phil-carousel-item`, `--phil-carousel-gap` |
| Les images | `--phil-gallery-column`, `--phil-gallery-gap`, `--phil-figure-column` ; `--phil-color-veil` et `--phil-color-on-veil` sous une légende, un point ou le contenu d'un héros posés sur une image |
| Les couleurs du code coloré | `--phil-hl-keyword`, `--phil-hl-string`, `--phil-hl-number`, `--phil-hl-comment`, `--phil-hl-name`, `--phil-hl-markup` |
| Le titre | `--phil-reveal-caret`, les durées par les valeurs du contrôleur, `.isRevealToggle` pour le bouton de pause |
| Des paires de contraste en plus | `new ContrastRule([new Pair(…)])` |
| Certaines couches seulement | Importer les fichiers sources de `css/` dans les mêmes couches, dans le même ordre |
