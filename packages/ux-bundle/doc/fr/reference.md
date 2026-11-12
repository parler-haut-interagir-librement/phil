<!-- phil-dev source-sha256=ada2957bdb190f9365e9307c2385e19c5b6f5b2cbc7ee208c8aaea21a7d3e146 -->
# Référence

| Type ou fichier | Sorte | Rôle |
|---|---|---|
| `PhilUxBundle` | `final class`, `AbstractBundle` | Alias `phil_ux` ; la clé `form_theme` ; le thème de formulaire ajouté en tête de `twig.form_themes` ; le répertoire de la feuille de style associé dans AssetMapper |
| `PhilUxBundle::FORM_THEME` | constante | `@PhilUx/form/phil_ux_layout.html.twig` |
| `PhilUxBundle::STYLESHEET` | constante | `phil-ux/phil-ux.css`, le chemin logique de la feuille de style |
| `PhilUxBundle::stylesheetDirectory()` | méthode statique | Le répertoire `dist/` de phil/ux-css |
| `templates/form/phil_ux_layout.html.twig` | thème de formulaire | Les blocs `form_row_render`, `form_help`, `form_errors` et `choice_widget_expanded` ([Le thème de formulaire](form-theme.md)) |

| Clé de configuration | Valeur par défaut | Sens |
|---|---|---|
| `phil_ux.form_theme` | `true` | Si le thème de formulaire est ajouté en tête de `twig.form_themes` |

Le bundle n'enregistre aucun service.
