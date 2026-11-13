<!-- phil-dev source-sha256=f89a4ae4abb4b018cd51258a1fb63ce0d35cd7322afbca97f5eca2ccffcab02b -->
# Points d'extension

Ce qu'une application configure, remplace ou utilise. Le bundle ne déclare aucun port PHP : son contrat, ce sont sa clé
de configuration, le chemin logique de sa feuille de style, sa couche de cascade et les variables qu'il définit.

| Point | Comment |
|---|---|
| Lier le thème | Actif par défaut sur chaque page de chaque tableau de bord ; `phil_ux_easy_admin.theme: false` le laisse à `configureAssets()` ([Installation](installation.md#configuration)) |
| Le chemin logique | `phil-ux-easyadmin/phil-ux-easyadmin.css`, `PhilUxEasyAdminBundle::STYLESHEET` |
| Un rôle de PHIL UX | `--phil-color-*`, `--phil-radius-*`, `--phil-font-*` redéfinis sur `:root, .ea-dark-scheme` hors de toute couche : le site et son administration suivent ([Le thème](theme.md#changer-le-thème)) |
| Une variable d'EasyAdmin | Redéfinie sur `:root, .ea-dark-scheme` hors de toute couche : elle l'emporte sur le thème |
| Une couche de l'application | Déclarée après `phil-easyadmin` |
| Les options de `Theme` d'EasyAdmin | Écrites par EasyAdmin hors de toute couche : elles l'emportent sur le thème |
| L'écouteur | Le service `phil_ux_easy_admin.theme_stylesheet`, un écouteur de `kernel.request` à la priorité -8, après l'écouteur du routeur d'EasyAdmin |
