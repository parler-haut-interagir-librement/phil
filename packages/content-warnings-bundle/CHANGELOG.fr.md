<!-- phil-dev source-sha256=c3dd609eaeda63a9f3f245db6f7c216ece1ab92d65a4686e2965e8e78aa226d2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/content-warnings-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Tests seulement.

### Modifié

- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Exige la dernière mineure de chaque dépendance (`symfony/service-contracts` 3.8), and of each development requirement
  (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilContentWarningsBundle`, alias d'extension `phil_content_warnings`, avec les clés `base_catalogue`,
  `catalogue_files`, `catalogue`, `default_display`, `fallback_locales` et `preferences_store` ; le catalogue est
  construit à la compilation du conteneur, si bien qu'une définition qui enfreint une règle arrête la construction.
- `CatalogueFactory`, `CatalogueLabels` et `WarningScreen`, qui construisent le catalogue, l'étiquettent dans la locale
  courante et décident pour le lecteur courant.
- `Contract\WarningPreferencesStore`, `SessionWarningPreferences`, qui ne démarre jamais de session pour lire et donne
  les valeurs par défaut pour les préférences qu'il ne peut pas relire, `InMemoryWarningPreferences`, remis à zéro entre
  les requêtes, et `Exception\PreferencesNotSaved`.
- `ContentWarningsColumnType`, le type de colonne `phil_content_warnings`.
- `ContentWarningsType` et `ContentWarningsTransformer`, qui gardent la gravité et la note d'un avertissement gardé et
  les avertissements de thèmes que le catalogue n'a plus ; le thème de formulaire
  `@PhilContentWarnings/form/theme.html.twig`, un groupe de champs par catégorie ; `WarningPreferencesType` et
  `WarningPreferencesTransformer`, qui gardent ce que le formulaire ne liste pas et la gravité d'un affichage gardé.
- `ContentWarningsExtension` : `content_warnings_decision`, `content_warning_label`, `content_warning_description`,
  `content_warning_category` ; le gabarit `@PhilContentWarnings/fold.html.twig`.
- Le contrôleur Stimulus `reveal` et la feuille de style `assets/styles/content-warnings.css`, son fondu seulement sans
  `prefers-reduced-motion: reduce`.
- Les libellés et les messages en anglais, en français, en espagnol, en allemand, en italien et en portugais, dans
  `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
