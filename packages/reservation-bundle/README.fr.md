<!-- phil-dev source-sha256=c716fd786bc8242db40259450b582005bcd947f00d69326a20b2148be2743487 -->
# phil/reservation-bundle

**Des réservations pour Symfony : les ressources et leurs réservations dans Doctrine, réservées une requête à la fois
par ressource.**

phil/reservation-bundle conserve les ressources réservables et leurs réservations dans Doctrine, et les réserve avec
les règles, les temps tampons, les heures et les conflits de `phil/reservation`. Une demande est retenue quelques
minutes ou réservée tout de suite ; elle s'exécute sous le verrou de sa ressource, pour que deux demandes ne prennent
jamais ensemble la dernière unité. Une retenue est confirmée ou expire ; une réservation est déplacée, redimensionnée
ou annulée ; les créneaux libres d'une période sont listés. Le formulaire de réservation donne chaque raison pour
laquelle une demande serait refusée, dans la langue de la personne qui lit. Deux commandes traitent les retenues qui
expirent et les rappels dus, les rappels partant sous forme de messages Messenger, et les réservations confirmées
apparaissent dans les vues de `phil/calendar-bundle`.

C'est le cœur de toute réservation qu'offre une application : des salles, des tables et des personnes directement, et des
rendez-vous, de la location d'équipement, de l'hébergement, les prêts d'une ludothèque ou des séances de jeu
d'évasion comme usages minces par-dessus. Il conserve des identifiants de titulaires, jamais leurs noms ni leurs
adresses.

## État

| Partie | État |
|---|---|
| `BookableResource` et `Reservation` dans Doctrine, moments en UTC | Implémenté et testé, sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| Des personnes réservées comme ressources, à l'égal des salles et des tables | Implémenté et testé |
| Le verrou de la ressource sous des requêtes concurrentes | Testé avec des processus qui se disputent la dernière unité sur MySQL et PostgreSQL |
| `Reservations` : vérifier, retenir, réserver, confirmer, déplacer, annuler, créneaux, retenues expirées | Implémenté et testé |
| `Reminders` et l'expéditeur Messenger | Implémenté et testé |
| `ReservationType` et les messages des raisons, en anglais et en français | Implémenté et testé |
| Les commandes, la source d'éléments du calendrier | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, le schéma. |
| [Configuration](doc/fr/configuration.md) | Les options de `phil_reservation`. |
| [Ressources](doc/fr/resources.md) | `BookableResource` : capacité, temps tampons, règles, heures, rappels, retenues, personnes. |
| [Réservations](doc/fr/reservations.md) | `Reservations` : retenir, réserver, confirmer, déplacer, annuler, le verrou, les événements. |
| [Formulaires et messages](doc/fr/forms.md) | `ReservationType` et `ViolationMessages`. |
| [Retenues et rappels](doc/fr/reminders.md) | Les commandes, `Reminders`, le message Messenger. |
| [Le calendrier](doc/fr/calendar.md) | Les réservations confirmées dans les vues de `phil/calendar-bundle`. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, événements et options qu'une application choisit ou remplace, et les usages construits sur le bundle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, sa suite d'intégration sur MySQL et PostgreSQL, et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/reservation-bundle
```

```php
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;

public function book(Reservations $reservations, ViolationMessages $messages, string $tableId): Response
{
    try {
        $hold = $reservations->hold($tableId, new DateTimeImmutable('2026-11-10 19:00'), new DateTimeImmutable('2026-11-10 22:00'), 4, $accountId);
    } catch (ReservationRefused $refused) {
        return $this->render('booking/refused.html.twig', ['reasons' => $messages->messages($refused->decision)]);
    }

    // … payment, then:
    $reservations->confirm($hold->id);
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, Doctrine ORM 3.7 et DBAL 4.5 ou plus récent. `symfony/console`,
`symfony/messenger` et `phil/calendar-bundle` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
