<!-- phil-dev source-sha256=24dbb306b7665f2a2072d7ab23d2130f98917e094a5a3269af13e9eec37b03cd -->
# phil/habits

**Des habitudes avec horaires, validations, séries qui ne punissent pas et une force lissée de 0 à 100 %.**

phil/habits garde les habitudes qu'un membre se donne — lire chaque jour, courir trois fois par semaine, appeler un ami
le dimanche — et ce qu'il en a fait, jour après jour. Une habitude a un horaire : chaque jour, une fois tous les N
jours, certains jours de la semaine, ou N fois par semaine ou par mois. Ses jours sont ceux du membre, dans le fuseau
horaire de l'habitude ; les instants sont enregistrés en UTC.

Un jour est validé comme fait, fait en partie vers une cible numérique — 5 pages sur 20 —, sauté, ou gelé d'avance pour
un voyage ou une maladie. La bibliothèque tire deux nombres des validations. La **série** compte les périodes
respectées d'affilée, la meilleure étant conservée ; un jour sauté ou gelé, et la période en cours, ne l'augmentent ni
ne la brisent. La **force**, affichée à côté de la série, est une moyenne à lissage exponentiel de la part faite de
chaque période, dont la mémoire diminue de moitié tous les quatorze jours : un jour manqué la baisse d'une fraction,
jamais jusqu'à zéro, et un jour fait en partie compte pour sa part.

La bibliothèque répartit des événements après une validation — l'habitude validée, une longueur de série atteinte, une
force atteinte —, pour qu'une couche de ludification qui les écoute puisse attribuer des points ou des badges ; elle
n'attribue jamais rien elle-même. Les membres sont désignés par un identifiant opaque et stable de l'application,
jamais par une adresse courriel.

C'est une bibliothèque PHP simple, bâtie sur l'horloge et le répartiteur d'événements PSR. Les habitudes et les
validations sont gardées derrière deux ports, avec des dépôts en mémoire et des dépôts sur Doctrine DBAL, testés sur
SQLite, MySQL 8.4 et PostgreSQL 17. Ses services ne gardent aucun état entre les appels et s'emploient sans risque dans
un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Horaires : quotidien, tous les N jours, jours de la semaine, N par semaine, N par mois, et leurs règles | Implémenté, testé et soumis au fuzzing |
| Validations : fait, fait en partie vers une cible, sauté, gelé ; revenir en arrière, annuler | Implémenté et testé |
| Séries, courante et meilleure, que les jours excusés et la période en cours épargnent | Implémenté et testé |
| La force de 0 à 100 %, et le port pour remplacer sa formule | Implémenté, testé et mesuré par banc d'essai |
| Événements pour une validation, une série et une force atteintes | Implémenté et testé |
| Dépôts en mémoire, et sur Doctrine DBAL pour SQLite, MySQL et PostgreSQL | Implémenté et testé |
| Les habitudes d'un membre, listées et effacées | Implémenté et testé |
| Points, badges ou récompenses pour les habitudes | Hors de la bibliothèque : un paquet de ludification écoute ses événements |
| Configuration, formulaires et pages | Hors de la bibliothèque : l'application, ou un bundle qui s'y ajoute, les fournit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les services à construire. |
| [Horaires](doc/fr/schedules.md) | Les sortes d'horaires, leurs règles et les périodes qu'ils découpent. |
| [Validations et séries](doc/fr/check-ins-and-streaks.md) | Valider, revenir en arrière, geler, annuler, et comment une série compte. |
| [Force](doc/fr/strength.md) | La formule de la force, sa demi-vie et comment la lire. |
| [Stockage](doc/fr/storage.md) | Les ports, les dépôts en mémoire et sur Doctrine DBAL, et leurs tables. |
| [Événements](doc/fr/events.md) | Les événements répartis, que peut écouter une couche de ludification. |
| [Sécurité et vie privée](doc/fr/security.md) | Ce qui est gardé d'un membre, l'effacement, les bornes de chaque entrée. |
| [Points d'extension](doc/fr/extension-points.md) | Les dépôts, la formule de la force, les jalons, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, et comment tester du code qui emploie des habitudes. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/habits
```

```php
use Phil\Habits\CheckInStatus;
use Phil\Habits\Schedule;

$habits->start('habit-7', $memberId, 'Read', Schedule::parse('3 per week'), new DateTimeZone('America/Toronto'));

$progress = $habits->checkIn('habit-7');

$progress->streak->current;      // 1 — weeks met in a row
$progress->streak->unit;         // StreakUnit::Week
$progress->strengthPercent();    // rises with each week met, falls a little with each week missed
$progress->current?->done;       // 1 check-in of the 3 this week asks

$habits->checkIn('habit-7', CheckInStatus::Skipped, day: $yesterday);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring`. `doctrine/dbal` 4.5 pour les dépôts en base de données.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
