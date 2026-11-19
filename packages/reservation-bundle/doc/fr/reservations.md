<!-- phil-dev source-sha256=8c07d77ca18d22d100fcc134e0ed6c7c2e3d81b2cfbe8d0003a7e7521e28be1a -->
# Réservations

`Reservations` est le service qu'appelle une application. Chaque méthode accepte la ressource ou la réservation par son
identifiant, et des moments dans n'importe quel fuseau horaire, lus à l'heure de la ressource.

| Méthode | Effet |
|---|---|
| `check(resource, start, end, quantity, replacing)` | La `Decision` qu'obtiendrait une demande maintenant, sans rien réserver |
| `hold(resource, start, end, quantity, holder)` | Une réservation retenue pendant la durée de retenue de la ressource |
| `book(resource, start, end, quantity, holder)` | Une réservation confirmée tout de suite |
| `confirm(id)` | Confirme une retenue ; une retenue expirée est d'abord vérifiée de nouveau |
| `reschedule(id, start, end, quantity)` | Déplace ou redimensionne une réservation retenue ou confirmée, vérifiée sans elle-même |
| `cancel(id)` | Libère ses unités |
| `slots(resource, from, until, minutes, quantity, step, limit)` | Les créneaux libres qu'obtiendrait une demande faite maintenant |
| `expireHolds(limit)` | Marque comme expirées les retenues dont le moment est venu |

Une demande refusée lève `ReservationRefused`, dont la `decision` contient chaque raison et les réservations qu'elle
rencontre. Une ressource inconnue ou inactive, ou une réservation inconnue, lève `NotFound` ; un changement que l'état
de la réservation ne permet pas — confirmer une réservation annulée, déplacer une réservation expirée — lève
`InvalidReservation`. Chaque exception implémente `Phil\Reservation\Exception\ReservationException`.

## Retenues

Une retenue garde ses unités jusqu'à `holdExpiresAt()` : une application retient pendant que le titulaire paie ou
remplit un formulaire, puis confirme. Passé son moment, une retenue ne compte plus contre la capacité, qu'elle soit
marquée expirée ou non. `confirm()` sur une retenue expirée la vérifie de nouveau, son préavis et sa fenêtre de
réservation comptés à partir du moment où elle a été faite, et la confirme si ses unités sont encore libres.

## Le verrou

`hold()`, `book()`, `confirm()` et `reschedule()` s'exécutent sous le verrou de la ressource : le dépôt Doctrine ouvre
une transaction et met d'abord à jour la ligne de la ressource, pour que la base de données fasse attendre toute autre
requête sur cette ressource jusqu'à ce que la réservation soit conservée. Deux demandes pour la dernière unité sont
tranchées l'une après l'autre, sur MySQL, PostgreSQL et SQLite également. Un refus annule la transaction et laisse le
gestionnaire d'entités ouvert.

La suite d'intégration le prouve sur MySQL 8.4 et PostgreSQL 17 : six processus, chacun sur sa propre connexion,
demandent la dernière unité d'une ressource au même instant ; un la réserve et cinq sont refusés. Le verrou ne lit rien
de périmé sur l'un ni l'autre : sur MySQL sous `REPEATABLE READ`, les réservations sont lues après l'obtention du verrou
de ligne, de sorte que l'instantané de la transaction contient déjà la réservation validée avant elle ; sur PostgreSQL
sous `READ COMMITTED`, chaque requête voit ce qui était validé à son début.

`cancel()` et `expireHolds()` libèrent des unités et ne prennent aucun verrou. Une réservation porte une version : un
changement enregistré sur une copie lue avant qu'un autre changement soit enregistré — une retenue annulée pendant que
l'exécution des expirations la marque expirée — échoue avec l'`OptimisticLockException` de Doctrine au lieu de
l'écraser. Relisez la réservation et réessayez.

### Une contrainte sous le verrou

Le verrou est la garantie ; une contrainte de base de données peut ajouter un filet dessous, contre des lignes écrites
hors du bundle. PostgreSQL en a une qui convient à une ressource de capacité un, une contrainte d'exclusion sur les
plages confirmées :

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE phil_reservation ADD CONSTRAINT phil_reservation_no_overlap
    EXCLUDE USING gist (resource WITH =, tsrange(starts_at, ends_at) WITH &&)
    WHERE (status = 'confirmed');
```

Elle ne connaît ni les capacités supérieures à un, ni les temps tampons, ni les retenues qui gardent leurs unités
quelques minutes : elle refuse donc moins que le bundle et ne le remplace jamais ; et elle lie chaque ressource de la
table, alors ne l'ajoutez que lorsque chaque ressource a une capacité de un. MySQL n'a pas d'équivalent. Le bundle ne
la crée pas.

## Moments et titulaires

Les moments — début, fin, création, fin d'une retenue — sont des colonnes du type `datetime_utc_immutable` de DBAL :
écrits en UTC quel que soit le fuseau horaire du moment donné, relus en UTC quel que soit celui du processus, pour que
deux serveurs de fuseaux différents voient le même instant. Ils sont rendus à l'heure de la ressource : `interval()`
est un `Interval` de `phil/reservation`. Les requêtes comparent leurs moments avec le même type. Le titulaire est un
identifiant que choisit l'application — un compte, une commande, une session — de 1 à 255 octets, jamais un nom ni une
adresse.

## Événements

Quand un répartiteur PSR-14 est enregistré comme `event_dispatcher`, chaque changement envoie un événement une fois
conservé : `ReservationHeld`, `ReservationConfirmed`, `ReservationRescheduled`, `ReservationCancelled` et
`ReservationExpired`. Chacun ne transporte que les identifiants de la réservation et de sa ressource ; un écouteur lit
le reste.
