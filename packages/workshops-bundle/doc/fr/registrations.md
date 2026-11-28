<!-- phil-dev source-sha256=ff1a8b0b1afce719cb78defa43165c5902328685bfa0a353c3319514633597e8 -->
# Séances et inscriptions

`Phil\WorkshopsBundle\Application\Workshops` est le service qu'une application appelle. Chaque méthode prend et
renvoie des identifiants et des entités ; aucune ne lit une requête ni un utilisateur.

## Ateliers et séances

```php
$workshop = $workshops->createWorkshop('Painting miniatures');
$session = $workshops->schedule($workshop->id, $start, $end, 'America/Toronto', capacity: 8, opensAt: $opens, closesAt: $closes);
```

| Valeur | Bornes |
|---|---|
| Le titre d'un atelier | De 1 à 200 caractères sur une ligne, sans espaces aux extrémités |
| La plage d'une séance | Sa fin après son début |
| Le fuseau horaire d'une séance | Un nom que PHP connaît, 64 octets au plus ; les moments de la séance reviennent sur son horloge |
| La capacité d'une séance | De 1 à 100 000 places |
| La fenêtre d'inscription | S'ouvre avant de se fermer ; se ferme au plus tard à la fin de la séance |

Une valeur hors bornes lance `Domain\Exception\InvalidWorkshop`. Un atelier inactif — `Workshop::activate(false)` — ne
prend aucune nouvelle séance ; les séances déjà planifiées se poursuivent.

| Méthode | Ce qu'elle fait |
|---|---|
| `reschedule($sessionId, $start, $end)` | Déplace une séance ; ses inscriptions sont conservées. La fenêtre doit toujours y tenir |
| `openRegistration($sessionId, $opensAt, $closesAt)` | Change la fenêtre ; `null` ouvre aussitôt, ou ferme au début de la séance |
| `resize($sessionId, $capacity)` | Change la capacité, jamais sous les places prises et offertes ; la liste d'attente, quand il y en a une, offre les places qu'elle libère |
| `cancelSession($sessionId)` | Annule une séance pour de bon : elle ne prend plus d'inscriptions, sa liste d'attente se ferme, et ses inscriptions restent pour que l'application prévienne ses participants |

Les moments sont gardés en UTC, dans les colonnes `datetime_utc_immutable` de Doctrine, quel que soit le fuseau horaire
du processus.

## S'inscrire

```php
$registration = $workshops->register($session->id, $accountId, seats: 2);
```

Le participant est un identifiant que choisit l'application — un compte, une commande, un billet —, de 1 à 255 octets.
Une adresse courriel est refusée : elle change et identifie une personne. Un participant déjà inscrit dans la
séance reçoit cette inscription, inchangée, sans événement : un formulaire envoyé deux fois n'inscrit qu'une fois. Une
fois annulée, le participant peut s'inscrire de nouveau.

La séance décide dans cet ordre, et la première raison qui s'applique est le refus, une
`Application\Exception\RegistrationRefused` avec son `RefusalReason` :

| Raison | Quand |
|---|---|
| `cancelled` | La séance est annulée |
| `not_open` | Avant l'ouverture de la fenêtre |
| `closed` | Dès que la fenêtre se ferme, ou dès le début de la séance quand elle n'a pas de moment de fermeture |
| `too_many_seats` | Plus de places que `max_seats`, ou que la séance n'en contient ; le paramètre `max` est le plus qu'elle en prend |
| `full` | Pas assez de places libres que personne ne réclame |

Quand la séance la prend, l'inscription est **confirmée**. Les places qu'elle prend sont les places libres que la liste
d'attente de la séance ne réclame pas : les places offertes et sans réponse, et, tant que quelqu'un attend, les places
que demandent ceux qui attendent. Personne ne double la file : une personne seule qui s'inscrit pendant qu'un groupe de
quatre attend quatre places est refusée comme `full`, même si une place est libre, et peut joindre la liste d'attente
derrière le groupe.

`seats($sessionId)` donne un `SeatCount` : `capacity`, `taken` par les inscriptions confirmées, `free`, `claimed` par la
liste d'attente, et `available` pour une nouvelle inscription ; `isFull()` dit si aucune ne l'est.

## La liste d'attente

Avec phil/waitlist-bundle installé et activé, et l'option `waitlist` active, chaque séance y a une liste d'attente,
ouverte quand le premier participant la joint : son sujet est `phil_workshops.session` et l'identifiant de la séance.
`Integration\Waitlist\SessionWaitlists` est le service à appeler :

```php
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;

try {
    $registration = $workshops->register($session->id, $accountId, seats: 2);
} catch (RegistrationRefused $refused) {
    if (RefusalReason::Full !== $refused->reason) {
        throw $refused;
    }

    $entry = $sessionWaitlists->join($session->id, $accountId, seats: 2); // phil/waitlist-bundle's Entry
}

// Later, the participant answers the offer the list made:
$registration = $sessionWaitlists->accept($entry->id);
```

| Méthode | Ce qu'elle fait |
|---|---|
| `join($sessionId, $participant, $seats)` | Met le participant sur la liste de la séance, derrière ceux qui y sont déjà ; un participant qui attend déjà ou tient une offre reçoit cette entrée |
| `accept($entryId)` | Accepte l'offre que tient l'entrée et confirme l'inscription du participant pour les places offertes, en une transaction |
| `of($sessionId)` | La liste de la séance, une fois que quelqu'un l'a jointe |
| `balance($sessionId)` | Donne à la liste les places libres que personne n'a prises ni reçues en offre, jusqu'à celles que demandent ses participants en attente, et reprend le reste |
| `balanceList($waitlistId)` | La même chose, à partir de la liste ; une liste qui n'est pas celle d'une séance est laissée intacte |
| `close($sessionId)` | Ferme la liste : plus d'entrées ; les offres tenues restent jusqu'à leur réponse ou leur expiration |

`join()` refuse comme l'inscription — `cancelled`, `not_open`, `closed`, `too_many_seats` — et avec `registered` quand le
participant tient déjà des places dans la séance. Les refus propres à la liste, comme une liste fermée, viennent sous la
forme du `WaitlistRefused` de phil/waitlist-bundle, et un participant qu'elle ne prend pas comme identifiant sous la
forme de son `InvalidWaitlist`.

`accept()` refuse une entrée qui ne tient aucune offre, ou dont l'échéance est arrivée, par le `WaitlistRefused` de
phil/waitlist-bundle ; une séance qui ne prend plus d'inscriptions par son `RefusalReason` ; et des places prises entre-temps
par une inscription faite sans passer par le service comme `full`. Un participant qui tient déjà des places dans la
séance reçoit cette inscription, et son entrée quitte la liste. L'inscription confirmée envoie `RegistrationConfirmed`,
comme celle que fait `register()`.

Les places circulent d'elles-mêmes entre la séance et sa liste : l'écouteur équilibre la liste quand une inscription est
annulée, quand la capacité change et quand une entrée la quitte, et la ferme quand la séance est annulée. Chaque
équilibrage reprend les places que tient la liste et libère de nouveau sa part, si bien que la politique de la liste
s'exécute sur les entrées qui attendent maintenant : avec `first_in_line`, un groupe de quatre garde son tour pendant que
deux places lui sont tenues, et les places vont à l'entrée suivante une fois que le groupe part. La liste offre des
places tant que la séance prend des inscriptions : une fois sa fenêtre fermée, les places libérées restent à la séance.

Accepter, refuser, expirer et quitter relèvent de phil/waitlist-bundle : `Waitlists::decline()`, `leave()`,
`lapseExpired()` et la commande `phil:waitlist:lapse`, dont elle offre elle-même les places libérées aux entrées
suivantes. Acceptez par `SessionWaitlists::accept()` plutôt que par `Waitlists::accept()`, qui ne donne les places à
personne.

Sans phil/waitlist-bundle, ou avec `waitlist: false`, une séance complète refuse comme `full` et rien ne réclame une
place libre.

## Annuler

```php
$workshops->cancel($registrationId);
```

Une inscription confirmée est annulée une seule fois, avec son moment `cancelledAt` ; une seconde annulation lance
`InvalidWorkshop`. Ses places vont à la liste d'attente de la séance quand quelqu'un attend, et à la prochaine inscription
sinon.

## Le verrou

`register()`, `cancel()`, `resize()` et `cancelSession()`, ainsi que `SessionWaitlists::join()`, `accept()` et
`balance()`, s'exécutent sous le verrou de la séance :
`RegistrationRepository::exclusively()` ouvre une transaction qui met d'abord à jour la ligne de la séance, puis relit la
séance. Une seconde requête sur la même séance attend que la première soit validée, puis voit ses places : deux requêtes
ne prennent jamais la dernière place ensemble, et une capacité changée entre-temps est celle qui est comptée. Un refus
annule la transaction.

`SessionWaitlists` prend d'abord le verrou de la séance, puis, par phil/waitlist-bundle, le verrou de la liste dans la même
transaction : toujours dans cet ordre, si bien que deux requêtes ne s'attendent jamais l'une l'autre. Les événements de
la liste qu'un appel provoque, comme `PlaceOffered`, partent dans cette transaction, avant sa validation.

## Événements

Chaque changement envoie un événement une fois enregistré, par le service `event_dispatcher` quand il existe. Chacun ne
porte que des identifiants ; un écouteur lit le reste par les dépôts. Les événements propres à la liste d'attente — `EntryJoined`, `PlaceOffered`,
`OfferAccepted`, `OfferLapsed`… — relèvent de phil/waitlist-bundle et concernent ses entrées.

| Événement | Porte | Quand |
|---|---|---|
| `RegistrationConfirmed` | `registrationId`, `sessionId` | Une inscription prend ses places : inscrite, ou une offre acceptée |
| `RegistrationCancelled` | `registrationId`, `sessionId` | Une inscription est annulée, avant que la liste d'attente offre les places qu'elle libère |
| `SessionResized` | `sessionId` | La capacité change, avant que la liste d'attente offre ou reprenne des places |
| `SessionRescheduled` | `sessionId` | Une séance est déplacée |
| `SessionCancelled` | `sessionId`, `workshopId` | Une séance est annulée |

## Messages

`Infrastructure\Translation\RefusalMessages` donne chaque refus et chaque statut dans la langue du lecteur, d'après les
messages du domaine `phil_workshops`, en anglais et en français :

```php
$messages->message($refused);                          // "From 1 to 4 seats can be taken per registration."
$messages->status(RegistrationStatus::Cancelled, 'fr'); // "Annulée"
```
