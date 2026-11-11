<!-- phil-dev source-sha256=b0e7a17f6313c28712c8e125c8a4f93687ba059174210822341e6cb8bb23e962 -->
# phil/recurrence

**Des dates qui se répètent selon une règle RFC 5545 : lisez-la, vérifiez-la, écrivez-la, listez ses occurrences.**

phil/recurrence lit les `RRULE` d'iCalendar : annuelles, mensuelles, hebdomadaires et quotidiennes, à un intervalle de
tant de périodes, par jour de la semaine avec son rang dans le mois ou l'année, par jour du mois compté depuis le début
ou la fin, par mois et par position dans l'ensemble, jusqu'à une date ou pour un nombre de fois. Elle vérifie une règle
selon la RFC, la réécrit en texte et la développe en occurrences, avec les dates qu'ajoute `RDATE` et sans celles que
retirent ses exceptions `EXDATE`, et lit et écrit leurs lignes. Elle peut aussi décrire une récurrence sous la forme
d'un `Schedule` de schema.org. Elle ne lit ni les fréquences horaires ou plus fines, ni les numéros de semaine, ni les
jours de l'année, ni les périodes de `RDATE`.

Elle s'adresse à toute application PHP dont les événements se répètent : une réunion hebdomadaire, une soirée de club
mensuelle, un festival annuel. Les règles suivent la RFC pour circuler telles quelles vers d'autres calendriers et en
revenir. La bibliothèque est écrite de zéro à partir de la RFC 5545 et ne dépend que de PHP et de `ext-mbstring`. Ce
n'est pas un calendrier : `phil/calendar-bundle` en est un, construit sur elle. Dans une application Symfony,
`phil/recurrence-bundle` y ajoute un formulaire, un type de colonne Doctrine et des fonctions Twig.

La conception repose sur deux types de valeur. `RecurrenceRule` contient une règle et refuse celle que la RFC ne permet
pas. `Recurrence` associe une règle à une première occurrence et donne les occurrences dans le fuseau horaire de cette
occurrence, en gardant son heure murale d'un changement d'heure à l'autre.

## État

| Partie | État |
|---|---|
| `RecurrenceRule` : lecture, vérification selon la RFC 5545, écriture | Implémenté et testé |
| `Recurrence` : occurrences, entre deux moments, la suivante, exceptions | Implémenté et testé |
| `RDATE` fusionnées aux occurrences hors de `COUNT` ; `RecurrenceDates` : lignes `RDATE` et `EXDATE` lues et écrites | Implémenté et testé |
| Les exemples de la RFC 5545, section 3.8.5.3, que lit la bibliothèque | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Règles](doc/fr/rules.md) | `RecurrenceRule`, ses parties, ce qui est vérifié, ce qui n'est pas lu. |
| [Occurrences](doc/fr/occurrences.md) | `Recurrence`, fuseaux horaires, fins, exceptions, dates ajoutées. |
| [Dates ajoutées et retirées](doc/fr/dates.md) | `RecurrenceDates`, les lignes `RDATE` et `EXDATE` lues et écrites. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données que l'application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/recurrence
```

```php
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

$rule = RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU;COUNT=6');
$meeting = Recurrence::of(new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), $rule);

$meeting->between(limit: 3);                  // 2026-11-10, 2026-12-08, 2027-01-12, at 19:00 in Toronto
$meeting->next(new DateTimeImmutable());      // the next one, or null
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
