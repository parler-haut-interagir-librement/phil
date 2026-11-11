<!-- phil-dev source-sha256=69f0aa26274b529766d2a2a9e781c890e4b7180d9686580b29ebda45ac1187d5 -->
# phil/opening-hours

**Les heures d'ouverture d'un lieu, et s'il est ouvert en ce moment.**

phil/opening-hours garde les heures d'un lieu sous forme d'objets-valeurs. Une semaine a ses heures, avec des plages
qui peuvent fermer après minuit. Des heures spéciales les remplacent pour un jour ou une période, comme un jour férié où
le lieu est fermé ou un été avec d'autres heures. La bibliothèque dit si le lieu est ouvert à un moment donné et quand
il ouvre de nouveau, dans le fuseau horaire du lieu. Elle regroupe la semaine en suites de jours qui ont les mêmes
heures, lit et écrit une forme texte que les gens saisissent, donne l'`openingHoursSpecification` de schema.org et
transforme les heures en tableau de textes pour une seule colonne JSON. Elle ne nomme pas les jours et n'écrit pas les
heures dans une langue : c'est ce que fait `phil/opening-hours-bundle` avec ICU. Elle ne contient pas non plus de
jours fériés : les heures spéciales sont propres au lieu.

Elle s'adresse à toute application qui répertorie des lieux que les gens visitent : une boutique, une bibliothèque, un
club, une salle. Un visiteur veut savoir si la porte est ouverte maintenant, et un moteur de recherche veut les heures
sous une forme qu'il sait lire.

C'est une simple bibliothèque PHP, utilisable dans tout code PHP, sans autre dépendance que `ext-mbstring`. Dans une
application Symfony, utilisez `phil/opening-hours-bundle`, qui ajoute un formulaire, un type de colonne Doctrine et des
fonctions Twig.

La conception repose sur de petits types immuables : `TimeOfDay`, `TimeRange`, `DayHours`, `SpecialHours` et
`OpeningHours`. Un moment est converti dans le fuseau horaire du lieu avant d'être lu : demander à 14 h 30 UTC en
janvier pour une boutique de Toronto revient à demander pour 9 h 30 là-bas.

## État

| Partie | État |
|---|---|
| `TimeOfDay`, `TimeRange`, `DayHours`, `SpecialHours`, `OpeningHours` | Implémenté et testé |
| `isOpenAt()`, `nextOpening()`, `groups()` | Implémenté et testé |
| `HoursText`, `OpeningHoursSchema`, `OpeningHoursArray` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Heures](doc/fr/hours.md) | Heures, plages, jours, heures spéciales et le fuseau horaire. |
| [Interroger](doc/fr/asking.md) | Ouvert maintenant, prochaine ouverture, les heures d'un jour, la semaine en suites. |
| [Texte, schema.org et stockage](doc/fr/text-schema-storage.md) | La forme texte, les spécifications de schema.org, le tableau. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/opening-hours
```

```php
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;

$weekday = HoursText::day('09:00-12:00, 13:00-17:00');
$hours = OpeningHours::of(
    [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => HoursText::day('09:00-17:00, 20:00-02:00')],
    HoursText::specials("2026-12-25 # Christmas"),
    'America/Toronto',
);

$hours->isOpenAt(new DateTimeImmutable());   // true or false
$hours->nextOpening(new DateTimeImmutable()); // the next opening, in Toronto
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
