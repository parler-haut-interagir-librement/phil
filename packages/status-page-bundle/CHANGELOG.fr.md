<!-- phil-dev source-sha256=06514e52df1772b578adec675b535e277816a40828214b5a0ca449de0d923a07 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/status-page-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Un test des stockages emploie un *stub* au lieu d'un *mock* qui n'attend rien, ce que PHPUnit signale.
- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31, `symfony/ux-twig-component` 3.8), and of each
  development requirement (`league/flysystem` 3.36, `league/flysystem-memory` 3.31, `phil/ux-kit` 0.5,
  `symfony/cache-contracts` 3.8).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `PhilStatusPageBundle` et sa configuration `phil_status_page` : le titre, la source, les composants, les
  enregistrements, l'historique, le cache, la disposition, le sous-dossier du kit et l'instantané.
- Le domaine : `ComponentStatus` et son ordre, `IncidentState`, `Component`, `Incident` avec ses mises à jour,
  `ScheduledMaintenance`, `StatusPage`, et `InvalidStatusRecord` et `UnknownSnapshotTarget` sous `StatusPageException`.
- `StatusPageBuilder`, qui assemble la page au présent de l'horloge à partir d'une source d'états et d'un stockage
  d'enregistrements.
- Les sources d'états : `DeclaredStatuses`, et `MonitoringStatuses`, qui lit les vérifications, les tâches et le mode
  maintenance de `phil/monitoring-bundle`, détecté par `MonitoringPass`, et garde les états dans un pool de cache.
- `RecordsReader` et les stockages d'enregistrements : `ConfiguredRecords` et `JsonFileRecords`, avec la cible de
  fuzzing `status-records`.
- La page à `/status`, son JSON à `/status.json` et son fil Atom à `/status.atom`, servis par `StatusPageController`
  sans script, ni témoin ni session ; `JsonDocument`, `AtomFeed` et `TwigPageRenderer`.
- Les gabarits, dessinés avec les recettes Card et Badge du kit PHIL UX, les fonctions Twig `phil_status_kit()` et
  `phil_status_variant()`, et les traductions en anglais et en français.
- L'instantané — `index.html`, `status.json`, `feed.atom` — écrit par `SnapshotPush` et donné aux cibles par
  `SnapshotPublisher` : `DirectoryPublisher`, `FlysystemPublisher`, ou un éditeur propre à l'application.
- `phil:status-page:push`, le message `PushStatusSnapshot` et son handler, et la planification `phil_status_page`.
- La recette Flex, sous `recipe/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
