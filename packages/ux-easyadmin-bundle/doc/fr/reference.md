<!-- phil-dev source-sha256=0195ee1f8a7cb33eb0eb4f877c6a93db14909e3682786cec341f6046cbea4be0 -->
# Référence

| Type ou fichier | Sorte | Rôle |
|---|---|---|
| `PhilUxEasyAdminBundle` | `final class`, `AbstractBundle` | Alias `phil_ux_easy_admin` ; la clé `theme` ; `assets/styles` associé sous `phil-ux-easyadmin/` ; l'écouteur |
| `PhilUxEasyAdminBundle::STYLESHEET` | constante | `phil-ux-easyadmin/phil-ux-easyadmin.css` |
| `PhilUxEasyAdminBundle::STYLES_DIR` | constante | Le répertoire qu'AssetMapper associe |
| `Infrastructure\EasyAdmin\ThemeStylesheet` | `final readonly class`, écouteur de `kernel.request` | Ajoute la feuille de style aux ressources CSS du contexte EasyAdmin de la requête |
| `Infrastructure\Stylesheet\ThemeSheet` | `final readonly class` | `build()` écrit la feuille de style unique depuis sa source et les fichiers de jetons ; `tokensDirectory()` trouve ceux de phil/ux-css |
| `assets/src/phil-ux-easyadmin.css` | source de feuille de style | L'ordre des couches, les importations des fichiers de jetons, la couche `phil-easyadmin` |
| `assets/styles/phil-ux-easyadmin.css` | feuille de style | Le fichier servi, écrit par `bin/build.php` ([Le thème](theme.md)) |
| `bin/build.php` | script | Écrit `assets/styles/phil-ux-easyadmin.css` |
| `recipe/0.1/` | recette Flex | Enregistre le bundle et écrit `config/packages/phil_ux_easy_admin.yaml` |

| Clé de configuration | Valeur par défaut | Sens |
|---|---|---|
| `phil_ux_easy_admin.theme` | `true` | Si chaque page de chaque tableau de bord lie la feuille de style |

| Service | Classe |
|---|---|
| `phil_ux_easy_admin.theme_stylesheet` | `ThemeStylesheet`, avec `theme: true` |
