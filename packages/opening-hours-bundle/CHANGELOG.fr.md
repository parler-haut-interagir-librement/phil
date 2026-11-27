<!-- phil-dev source-sha256=b49d7d00b6d9bbbd8173b203a4d8bf05f6c008d2f53db2aba0ed871645b934f8 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/opening-hours-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilOpeningHoursBundle`, alias d'extension `phil_opening_hours`, avec la clé `default_timezone`.
- `OpeningHoursColumnType`.
- `OpeningHoursType` et ses transformateurs.
- `Intl`, qui garde ses formateurs ICU par langue et par style.
- `OpeningHoursExtension`, qui prend une `ClockInterface`, le service `clock` du conteneur, sans se rabattre sur l'heure
  du système : `opening_hours_week()` donne à chaque rangée un `heading`, ses jours avec seulement leur première lettre
  en capitale, et `opening_hours_specials()` lit le moment dans le fuseau horaire du lieu ; le gabarit
  `@PhilOpeningHours/table.html.twig`, qui affiche l'en-tête de chaque rangée.
- Les libellés et les messages en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; le README, `doc/installation.md` et les
  suggestions de Composer disent qu'avec Twig le conteneur a besoin d'un service `clock`, celui de `symfony/clock`.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
