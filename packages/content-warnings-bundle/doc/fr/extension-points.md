<!-- phil-dev source-sha256=03797d01de693434e9ae77e12a2f6a296cf813a998c60abdc6168c1f1d78077f -->
# Points d'extension

Ce qu'une application configure, implémente, remplace ou écoute. Le catalogue, les avertissements, les préférences et
les décisions sont ceux de `phil/content-warnings`.

## Le catalogue

| Point | Comment |
|---|---|
| Les thèmes et les catégories | `base_catalogue`, `catalogue_files` et `catalogue` — voir [Configuration](configuration.md) |
| Les langues des libellés | Les libellés des définitions ; `fallback_locales` |
| La lecture du catalogue | Le service `Phil\ContentWarnings\Catalogue`, et `CatalogueLabels` pour les libellés dans la locale courante |

## Préférences

| Point | Comment |
|---|---|
| L'endroit où les préférences sont conservées | Une implémentation de `WarningPreferencesStore`, nommée par `preferences_store` — voir [Préférences](preferences.md) |
| L'affichage pour un lecteur qui n'a rien choisi | `default_display` |
| La décision dans le code | `WarningScreen::decide()` pour le lecteur courant, ou le `WarningMatcher` de la bibliothèque pour un autre |

## Stockage et formulaires

| Point | Comment |
|---|---|
| L'endroit où les avertissements sont conservés | Le type de colonne `phil_content_warnings` — voir [Doctrine](doctrine.md) |
| Le choix des avertissements | `ContentWarningsType` et ses options ; une sévérité ou une note par les propres champs de l'application |
| Le choix des préférences | `WarningPreferencesType` et ses options |
| Les libellés | Le domaine de traduction `phil_content_warnings` : les traductions d'une application remplacent celles qui sont fournies |
| Davantage que ce que font les champs | Une extension de type de formulaire de l'application sur `ContentWarningsType` ou `WarningPreferencesType` |

## Affichage

| Point | Comment |
|---|---|
| Le balisage | `@PhilContentWarnings/fold.html.twig` remplacé dans `templates/bundles/PhilContentWarningsBundle/`, ou le balisage propre à l'application à partir de `content_warnings_decision` |
| Le thème de formulaire | `@PhilContentWarnings/form/theme.html.twig` ; un thème de l'application placé après lui remplace son bloc |
| Le comportement à la révélation | `phil--content-warnings-bundle--reveal` répartit `revealed` et `concealed`, et offre `reveal` et `conceal` |
| L'apparence | `assets/styles/content-warnings.css`, ses classes `phil-content-warning*` |

## Autres outils

Un outil de sécurité lit le même catalogue et les mêmes préférences : `Catalogue::themes()` et `categories()` pour les
thèmes, et `WarningPreferences::themesWith(Display::Hide)` et `themesWith(Display::Fold)` d'un lecteur pour les lignes
et les voiles.
