<!-- phil-dev source-sha256=d4d66e7baa948a282a159167e29fe3b79ce967f16b532951bca6d4da66222a14 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/bot-detection-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Tests seulement.

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.
- L'en-tête de `assets/src/detectors.js` et la page sur les signaux du navigateur décrivent les détecteurs sans cette
  formulation.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Domain\Verdict\Verdict`.
- `Domain\Friction\Friction`, `Domain\Friction\FrictionPolicy`.
- `Domain\Counter\RobotVerdictCounter` (port, implémenté par l'application).
- `Application\VerdictHandler`.
- Les détecteurs du navigateur (`assets/src/detectors.js`) et le contrôleur Stimulus `verdict`, qui écrit `human` ou
  `robot` sur l'élément racine et dans son champ ; testés sous l'exécuteur de tests de Node (`tests/js`).
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
