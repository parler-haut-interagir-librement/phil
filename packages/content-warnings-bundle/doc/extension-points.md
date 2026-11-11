# Extension points

What an application configures, implements, replaces or listens to. The catalogue, warnings, preferences and decisions
are `phil/content-warnings`'s.

## The catalogue

| Point | How |
|---|---|
| The themes and categories | `base_catalogue`, `catalogue_files` and `catalogue` — see [Configuration](configuration.md) |
| The languages of labels | The labels of the definitions; `fallback_locales` |
| Reading the catalogue | The service `Phil\ContentWarnings\Catalogue`, and `CatalogueLabels` for labels in the current locale |

## Preferences

| Point | How |
|---|---|
| Where preferences are kept | An implementation of `WarningPreferencesStore`, named by `preferences_store` — see [Preferences](preferences.md) |
| The display for a reader who chose nothing | `default_display` |
| Deciding in code | `WarningScreen::decide()` for the current reader, or `WarningMatcher` of the library for another |

## Storage and forms

| Point | How |
|---|---|
| Where warnings are kept | The column type `phil_content_warnings` — see [Doctrine](doctrine.md) |
| Picking warnings | `ContentWarningsType` and its options; a severity or a note through the application's own fields |
| Choosing preferences | `WarningPreferencesType` and its options |
| Labels | The `phil_content_warnings` translation domain: an application's translations override the shipped ones |
| More than the fields do | A form type extension of the application's on `ContentWarningsType` or `WarningPreferencesType` |

## Display

| Point | How |
|---|---|
| The markup | `@PhilContentWarnings/fold.html.twig` overridden in `templates/bundles/PhilContentWarningsBundle/`, or the application's own from `content_warnings_decision` |
| The form theme | `@PhilContentWarnings/form/theme.html.twig`; a theme of the application's listed after it overrides its block |
| Behaviour on reveal | `phil--content-warnings-bundle--reveal` dispatches `revealed` and `concealed`, and offers `reveal` and `conceal` |
| The look | `assets/styles/content-warnings.css`, its classes `phil-content-warning*` |

## Other tools

A safety tool reads the same catalogue and preferences: `Catalogue::themes()` and `categories()` for the themes, and a
reader's `WarningPreferences::themesWith(Display::Hide)` and `themesWith(Display::Fold)` for lines and veils.
