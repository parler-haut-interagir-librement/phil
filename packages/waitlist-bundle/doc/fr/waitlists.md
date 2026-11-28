<!-- phil-dev source-sha256=2ed4b753319f829b0f5a593c32f9a0965d0b0d1a51a637548a881b771de882f3 -->
# Listes d'attente

Tout passe par le service `Phil\WaitlistBundle\Application\Waitlists`.

## Ouvrir une liste

Une liste appartient à un sujet que l'application nomme : un genre — lettres minuscules, chiffres, `_`, `.` et `-`,
comme `session` ou `event.ticket` — et un identifiant. Un sujet a une liste : `open()` la crée au premier appel et
redonne ensuite la même liste, inchangée.

```php
$waitlist = $waitlists->open('session', $session->id);
$waitlist = $waitlists->open('workshop', $workshop->id, offerWindow: 3600);
$waitlist = $waitlists->open('convention', $convention->id, WaitlistMode::Draw);

$waitlists->ofSubject('session', $session->id); // the list, or null
```

La table contient une liste par sujet : quand deux requêtes ouvrent en même temps la liste d'un même sujet, la seconde
échoue sur cette contrainte d'unicité et trouve la liste en rappelant `open()`.

La liste ne compte jamais la capacité du sujet : c'est l'application qui la compte, et qui dit à la liste quand des
places se libèrent. Le moment où la liste s'ouvre est aussi le choix de l'application : quand la dernière place est
prise, ou dès le début.

## S'inscrire et la position

```php
$entry = $waitlists->join($waitlist->id, $accountId, places: 2);
$standing = $waitlists->standing($entry->id);
$standing->status;          // EntryStatus::Waiting
$standing->position;        // 1 for the next in line, null once it is not waiting
$standing->offerExpiresAt;  // the deadline of the offer it holds, or null
```

Un participant est un identifiant que donne l'application — un compte, une commande, un billet — : lettres, chiffres,
`_`, `.`, `:` et `-`, 190 caractères au plus. Une adresse courriel est refusée : elle change, et elle identifie une
personne. Une entrée demande une place ou plus, au plus `max_places`.

Un participant qui s'inscrit sur une liste où il est déjà — en attente ou détenant une offre — récupère la même
entrée : son rang et ses places ne changent pas. Une fois sorti de la liste, il s'inscrit de nouveau à la fin.

| Refus | `WaitlistRefused::$reason` |
|---|---|
| La liste est fermée | `RefusalReason::Closed` |
| Plus de places que `max_places` | `RefusalReason::TooManyPlaces`, avec `['max' => …]` dans `$parameters` |

Les raisons sont des valeurs que l'application met dans la langue de son lecteur.

## Remettre des places

Quand des places se libèrent dans le sujet — une inscription annulée, la capacité augmentée —, l'application les remet :

```php
$offered = $waitlists->release($waitlist->id, 2); // the entries offered places
```

La liste les ajoute aux places qu'elle garde, puis demande à sa politique à quelles entrées en attente les offrir.
Chaque entrée choisie détient une offre jusqu'à l'échéance de la liste : maintenant plus sa fenêtre d'offre. Les places
qui ne conviennent à personne restent gardées, et vont à la première entrée à qui elles conviennent : une qui
s'inscrit, ou une plus loin quand une offre rend des places.

`FirstInLine`, la valeur par défaut, offre les places strictement par rang et s'arrête à la première entrée à qui elles
ne conviennent pas : un groupe de quatre garde son tour, et les places attendent qu'elles lui conviennent.
`FirstThatFits` passe par-dessus et offre les places à celles qui suivent et à qui elles conviennent ; le groupe garde
son rang pour les places suivantes.

Quand le sujet perd des places que la liste garde — la capacité réduite, l'événement annulé —, reprenez-les :

```php
$waitlists->reclaim($waitlist->id, 3); // the places taken back, at most those held
```

## Les offres

| Appel | Quand | Ensuite |
|---|---|---|
| `accept($entryId)` | Avant l'échéance | `OfferAccepted` : l'application donne les places au participant |
| `decline($entryId)` | Tant que l'offre est détenue | Les places vont aux entrées suivantes |
| `leave($entryId)` | En attente ou détenant une offre | Les places d'une offre vont aux entrées suivantes |
| `lapseExpired()`, ou `phil:waitlist:lapse` | Après l'échéance | L'offre expire, ses places vont aux entrées suivantes |

`accept()` et `decline()` refusent une entrée qui ne détient aucune offre, avec `RefusalReason::NoOffer` ; `accept()`
refuse une offre dont l'échéance est arrivée, avec `RefusalReason::OfferExpired`, même avant que la commande l'ait fait
expirer. Une entrée sortie de la liste reste dehors : une offre expirée ne remet pas l'entrée dans la file.

Accepter, c'est là où finit la liste d'attente et où commence l'application : l'application écoute `OfferAccepted`, ou
agit après le retour de `accept()`, et donne les places — une inscription confirmée, un billet émis, une commande
autorisée.

## Les tirages

Une liste en mode tirage n'offre rien avant son tirage. Les entrées s'inscrivent comme dans une file ; les places
remises avant le tirage sont gardées. Ensuite :

```php
$waitlists->draw($waitlist->id, $seed); // the seed to publish; a random one when null
$waitlist->drawSeed();                  // published with the list
```

Chaque entrée en attente au moment du tirage est classée selon le SHA-256 du germe, d'un saut de ligne et de son
identifiant, en hexadécimal, du plus bas au plus haut : quiconque a le germe et les identifiants les ordonne de
nouveau. Une liste est tirée une seule fois. Les places gardées sont alors offertes dans l'ordre du tirage, et ceux qui
s'inscrivent ensuite attendent derrière les entrées tirées, dans l'ordre de leur inscription. Un germe compte de 8 à
64 lettres, chiffres, `_`, `.`, `:` et `-` ; publiez-le après la fermeture des inscriptions, ou engagez-vous à
l'avance — annoncez son empreinte, révélez-le au tirage — pour que personne ne puisse le choisir afin de favoriser une
entrée.

## Fermer

```php
$waitlists->close($waitlist->id);  // no more entries; those in the list keep their turn and their offers
$waitlists->reopen($waitlist->id);
```

## Lire une liste

```php
$waitlists->count($waitlist->id);                       // ListCount: places waiting, offered, held
$waitlists->entries($waitlist->id, EntryStatus::Waiting); // by rank, at most `batch`
$waitlists->entry($entryId);
```

## Le verrou

Chaque requête qui change une liste s'exécute sous le verrou de la liste : une transaction qui met d'abord à jour la
ligne de la liste, puis relit la liste. Une seconde requête sur la même liste attend que la première valide, puis lit
ce qu'elle a enregistré ; les entrées lues à l'intérieur du verrou sont relues dans la base de données. Deux requêtes
n'offrent jamais la même place, et un refus annule toute la requête.

Les services ne gardent aucun état entre les requêtes : ils sont sûrs dans un *worker*, comme le mode *worker* de
FrankenPHP.

## Événements

| Événement | Quand |
|---|---|
| `EntryJoined` | Une entrée s'est inscrite sur une liste |
| `PlaceOffered` | Des places ont été offertes à une entrée, détenues jusqu'à son échéance |
| `OfferAccepted` | Une entrée a accepté son offre |
| `OfferDeclined` | Une entrée a refusé son offre |
| `OfferLapsed` | L'échéance d'une offre est passée sans réponse |
| `EntryLeft` | Une entrée a quitté sa liste |
| `WaitlistDrawn` | Le tirage d'une liste a été fait |

Chacun ne porte que des identifiants — celui de l'entrée et celui de la liste, ou celui de la liste —, et part une fois
le changement validé. Un écouteur lit le reste par `Waitlists::entry()` et `Waitlists::waitlist()`. Acheminés vers
Messenger, ils restent petits et ne portent rien de personnel.
