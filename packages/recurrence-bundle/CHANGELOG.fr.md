<!-- phil-dev source-sha256=9bdcee145a6919d0593136e06be3e92ea97ec8aa4195cc8f3fc6427801636127 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/recurrence-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

Tests seulement.

### Modifié

- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-24

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Exige la dernière mineure de chaque dépendance (`symfony/translation-contracts` 3.8), and of each development
  requirement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `RuleDescription`, qui garde un formateur ICU par langue, motif et fuseau horaire, 64 au plus ; `describe()` et le
  filtre `rrule_text` prennent le fuseau horaire de la première occurrence, dans lequel une heure de fin est écrite
  comme un jour. Les mots des positions depuis la fin : *l’avant-dernier dimanche*, *le 3e jour en partant de la fin*,
  *the 3rd Saturday from the end*, *keeping the last and the first*.
- Les messages ICU et les libellés en anglais et en français, dans `translations/` ; les messages de positions
  `recurrence.nth_day` et `recurrence.set_position` prennent `n` et `from_end`.
- `RecurrenceRuleColumnType`.
- `PhilRecurrenceBundle`, alias d'extension `phil_recurrence`.
- `RecurrenceRuleType` et `RecurrenceRuleTransformer`, qui redonne la règle qu'il a montrée quand ses champs reviennent
  avec le même sens, si bien qu'enregistrer un formulaire sans toucher à une règle plus riche — *le vendredi 13*, des
  positions d'ensemble, un début de semaine, une heure de fin — la garde telle quelle. Les champs `interval` et `count`
  vont de 1 à 9999, et une règle au-delà rend le formulaire invalide au lieu d'être stockée.
- Le contrôleur Stimulus `recurrence`, dans `assets/`.
- `RecurrenceExtension`, avec la fonction Twig `rrule_schema` : des données schema.org pour le JSON-LD.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
