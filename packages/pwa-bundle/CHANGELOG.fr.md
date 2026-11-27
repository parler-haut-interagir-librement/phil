<!-- phil-dev source-sha256=9b5bd150b596fcd604d2793143621f8083fd19c05616f0403f7fa7da7e7449e1 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/pwa-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31), and of each development requirement
  (`symfony/stimulus-bundle` 3.8).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `PhilPwaBundle` et sa configuration `phil_pwa` : le manifeste, le service worker, la disposition de la page hors ligne
  et le cache du manifeste.
- Le manifeste : `Manifest`, `Icon`, `Shortcut`, `DisplayMode`, `Orientation` et `IconPurpose`, chaque membre vérifié,
  les chemins résolus comme le fait un navigateur, lu par `ManifestReader`, avec la cible de fuzzing `manifest`, et
  écrit par `ManifestDocument` à `/manifest.webmanifest`, les icônes aux adresses que donne `AssetUrls`.
- Le service worker : `WorkerSettings`, `CacheRule` et `CacheStrategy` ; `ServiceWorkerScript`, qui sert à `/sw.js` les
  réglages, leur version et l'`importScripts()` du worker servi par AssetMapper ; le worker du bundle, avec sa liste de
  précache versionnée, les navigations réseau d'abord, les règles cache d'abord et réseau d'abord, la page hors ligne et
  l'activation sans attente ; les réponses marquées `private` ou `no-store` jamais gardées.
- `PrecacheProvider`, autoconfiguré, pour les chemins d'autres bundles.
- La page hors ligne à `/offline`, en anglais et en français, et les fonctions Twig `phil_pwa_head()`,
  `phil_pwa_manifest_path()`, `phil_pwa_service_worker_path()` et `phil_pwa_scope()`.
- Le contrôleur Stimulus `phil--pwa-bundle--pwa` : l'enregistrement, l'installation, la mise à jour, la connexion et le
  plein écran.
- Le contrôleur Stimulus `phil--pwa-bundle--presentation` : une page sur un second écran par l'API Presentation, ou une
  fenêtre à part et un BroadcastChannel.
- La recette Flex, sous `recipe/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
