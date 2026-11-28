<!-- phil-dev source-sha256=47b9effea5a8d22eb75a95cdbf2219f23fbd2a059b340a604e17351967ac8d7b -->
# phil/workshops-bundle

**Ateliers pour Symfony : des séances à nombre de places fixe, des inscriptions qui ne dépassent jamais la capacité et
une liste d'attente qui offre tour à tour les places libérées.**

phil/workshops-bundle garde dans Doctrine des ateliers, les séances données pour chacun et les inscriptions à ces
séances. Une séance a une plage horaire sur l'horloge de son propre fuseau horaire, une capacité et une fenêtre
pendant laquelle elle prend des inscriptions. Un participant s'inscrit pour une place ou plusieurs : l'inscription est
confirmée quand les places sont libres et que personne en attente ne les réclame, et refusée comme complète sinon.
Chaque inscription s'exécute sous le verrou de sa séance, si bien que deux requêtes ne prennent jamais la dernière
place ensemble ; s'inscrire deux fois redonne la même inscription. Avec phil/waitlist-bundle, chaque séance complète a
une liste d'attente : les places libérées par une annulation ou par une capacité accrue sont offertes tour à tour,
chaque offre étant tenue jusqu'à une échéance, et une offre acceptée devient une inscription confirmée. Les raisons
pour lesquelles une inscription est refusée sont données dans la langue du lecteur, et chaque changement est un
événement qui ne porte que des identifiants.

Il convient à toute application qui offre des cours, des ateliers ou des séances à places limitées : peinture, cuisine,
atelier dans un congrès, cours donné plusieurs fois. Il garde des identifiants de participants, jamais leurs noms ni
leurs adresses. Ce qu'un atelier ajoute — une description, un prix, le matériel, un âge — reste dans les tables propres
à l'application, par l'identifiant de l'atelier.

## État

| Partie | État |
|---|---|
| `Workshop`, `WorkshopSession` et `Registration` dans Doctrine, moments en UTC | Implémenté et testé sur SQLite |
| `Workshops` : planifier, déplacer, redimensionner, annuler une séance ; s'inscrire et annuler sous le verrou de la séance | Implémenté et testé |
| Une liste d'attente par séance avec phil/waitlist-bundle, `SessionWaitlists` et le port `SeatClaims` | Implémenté et testé sur SQLite |
| Les refus et les statuts en anglais et en français | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, le schéma. |
| [Configuration](doc/fr/configuration.md) | Les options de `phil_workshops`. |
| [Séances et inscriptions](doc/fr/registrations.md) | Planifier, la fenêtre d'inscription, s'inscrire, la liste d'attente de phil/waitlist-bundle, annuler, le verrou, les événements. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements, les messages et les options qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/workshops-bundle
```

```php
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Infrastructure\Translation\RefusalMessages;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;

$workshop = $workshops->createWorkshop('Painting miniatures');
$session = $workshops->schedule(
    $workshop->id,
    new DateTimeImmutable('2026-12-05 13:00', new DateTimeZone('America/Toronto')),
    new DateTimeImmutable('2026-12-05 16:00', new DateTimeZone('America/Toronto')),
    'America/Toronto',
    capacity: 8,
);

try {
    $registration = $workshops->register($session->id, $accountId, seats: 2); // confirmed
} catch (RegistrationRefused $refused) {
    $message = $messages->message($refused); // "This session is full.", in the reader's language

    // With phil/waitlist-bundle: a place in line, offered the seats that free up.
    $entry = $sessionWaitlists->join($session->id, $accountId, seats: 2);
}

$workshops->seats($session->id); // SeatCount: capacity, taken, free, claimed, available
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.8 ou une version plus
récente ; phil/waitlist-bundle pour les listes d'attente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
