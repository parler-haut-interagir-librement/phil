<!-- phil-dev source-sha256=131ac0bf441dab08284881aee4a487c4ca32d11291d66dab810ab8ce9896fca0 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/editorial-calendar` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `PlannedItem` : une publication planifiée, son contenu par `ResourceReference`, sa langue, son auteur, sa section, son
  état et son moment gardé en UTC, avec `plan()`, `restore()`, `transitionTo()`, `reschedule()`, `edit()` et
  `withoutSection()`.
- `ItemState` et son workflow : idée, rédaction, prêt, planifié, publié, retiré ; un élément planifié a toujours un
  moment.
- `AuthorId` : un auteur nommé par un identifiant stable, jamais une adresse courriel.
- `PublicationBatch` : des éléments publiés ensemble, à un moment en UTC ou à la main.
- `EditorialCalendar` : les éléments et les lots par identifiant, immuable ; `applyBatch()` publie chaque élément d'un
  lot ou aucun, `BatchFailed` nommant l'élément qui a refusé ; un élément d'un lot n'est jamais publié seul ;
  `dueBatches()`, `manualBatches()`, `unscheduled()` et `moment()`.
- `CalendarView`, `Period`, `Day`, `Entry` et `EntryKind` : des vues par jour, par semaine et par mois dans un fuseau
  horaire, justes à travers les changements d'heure, des semaines à partir de n'importe quel premier jour, et des listes
  entre deux moments ; chaque lot est une entrée avec ses éléments.
- `Filter` : les éléments de certains auteurs, sections ou états, pour chaque vue et pour `unscheduled()` ; un filtre
  non vide laisse de côté les transitions planifiées ailleurs.
- `TransitionReader`, `PlannedTransition` et `NoTransitions` : le port par lequel les vues lisent les transitions
  planifiées ailleurs.
- `Review`, `ReviewRules`, `Issue` et `IssueKind` : le contenu répété dans une même langue, les éléments planifiés alors
  qu'ils sont encore des idées ou des brouillons, les publications plus rapprochées qu'un espacement, et les trous plus
  longs qu'un maximum.
- `ICalendarWriter` : les publications comme flux iCalendar (RFC 5545), un événement par élément seul et par lot,
  échappé et plié à 75 octets.
- `EditorialCalendarException`, `InvalidItem`, `InvalidTransition`, `UnknownItem` et `BatchFailed`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, avec un gabarit qui dessine une vue avec
  Twig et les recettes du kit PHIL UX.
- Les bancs d'essai d'un mois, de sa révision et de l'exportation d'une année sur 5 000 éléments.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
