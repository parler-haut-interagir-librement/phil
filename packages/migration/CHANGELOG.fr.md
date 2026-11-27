<!-- phil-dev source-sha256=e734d9b1383df391a964a2e87cd2eaeb359d3d590310be5180be0463cf9428e7 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/migration` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6).

## 0.1.1-alpha - 2026-11-18

Une note qu'apportent plusieurs migrations d'une exécution est annoncée une fois. Voyez [Ce qu'annonce une
exécution](doc/fr/reference.md#ce-quannonce-une-exécution).

### Corrigé

- `Release::notesIn()` donne chaque note une fois, là où elle vient en premier : une note qu'apportent deux migrations
  de l'exécution, comme `* Libraries updated.` ajoutée par une aide de l'application, n'est plus annoncée deux fois. Les
  notes sont comparées sans les espaces autour d'elles, locale par locale ; les autres notes gardent leur ordre.
- `Release::newsIn()` donne chaque nouvelle une fois de la même façon, quand son titre, ses lignes, chacune sans les
  espaces autour d'elle, et son adresse sont les mêmes.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AnnouncingMigration`, l'interface `Announcing` et le trait `Announces` : `note()` et `news()` dans une migration.
- `Announcements`, gardées par migration jusqu'à la fin de l'exécution, `Release`, `News`, `InvalidAnnouncement` et
  `AnnouncementsMissing`.
- `Channel` et `Content` : un transport, une locale, les notes ou les nouvelles, des titres numérotés avec `{counter}`,
  des messages coupés à une longueur.
- `Announcer`, `Publisher` et `ChatterPublisher` ; un canal qui échoue est journalisé et les autres sont quand même
  avisés.
- `AnnouncingMigrationFactory`, qui donne les annonces à chaque migration `Announcing`.
- `AnnouncingMigrator`, qui annonce une exécution une fois terminée : rien pour un essai à blanc, ni pour une exécution
  échouée qui était tout ou rien ; les migrations qui restent pour une exécution échouée qui ne l'était pas.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
