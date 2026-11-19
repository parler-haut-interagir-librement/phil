<!-- phil-dev source-sha256=c30d27283d329d66bb08ff3e1c494a20e548a1b2a72d5fd0bc6edd28b2c32683 -->
# Le flux

`config/routes.php` déclare deux routes, `GET` : le flux du calendrier, servi par `CalendarFeedController`, et celui
d'un membre, servi par `PersonalFeedController`.

| Route | Chemin | Ce qu'elle répond |
|---|---|---|
| `phil_calendar_ics` | `/calendar.ics` | Les événements sous forme de fichier iCalendar auquel s'abonner |
| `phil_calendar_personal_ics` | `/personal/{token}.ics` | Le calendrier propre à un membre, à l'adresse de son secret |

## Le flux du calendrier

Elle accepte `host=kind:reference` et `tag=word` pour garder les événements d'un organisateur ou d'une étiquette.

Les événements qui ont une occurrence d'il y a un mois jusqu'à dans un an, écrits par `ICalendar` comme le dit la
RFC 5545 : un `VEVENT` chacun, sa règle en `RRULE`, ses dates ajoutées en `RDATE` et ses exceptions en `EXDATE` ; les heures avec leur `TZID`, en UTC,
ou en dates pour les journées entières ; la fin d'une règle du même genre que le début, comme le demande la RFC 5545
§3.3.10 (le jour de fin d'un événement à heure s'écrit comme le dernier moment de ce jour dans son fuseau horaire, en
UTC, `UNTIL=20270101T045959Z`, et l'heure de fin d'un événement d'une journée entière comme son jour) ; les textes
échappés, les lignes repliées à 75 octets entre les caractères ; `STATUS:CANCELLED` pour un événement annulé. Le `name`
configuré est le `X-WR-CALNAME` du calendrier. `ICalendarResponse` le sert en `text/calendar`.

Seuls les événements sont rassemblés : chacun est lu jusqu'à sa première occurrence de la période, jamais à travers
toutes. La réponse porte `Cache-Control: public, no-cache` avec un `ETag` fait des événements qu'elle contient et de
leurs versions, et du nom. Un calendrier ou un cache partagé qui le renvoie dans `If-None-Match` reçoit un
`304 Not Modified`, sans que le fichier soit écrit ; l'étiquette change quand un événement est modifié, ou entre dans
l'année ou en sort.

`Calendar::events($from, $through, $hostKind, $hostReference, $tag)` donne ces événements pour le flux propre à une
application, et `ICalendar::of($events, $stamp, $product, $name)` écrit le même fichier pour la sélection propre à une
application.

Les deux flux lisent leurs événements dans les limites du [budget](events.md#le-budget-dune-requête) de la requête :
au-delà, le fichier contient les événements lus jusque-là.

## Le flux d'un membre

`/personal/{token}.ics` répond au membre dont le secret est `token` — 64 chiffres hexadécimaux, émis par
`PersonalFeeds` ([Membres](members.md#le-flux-dun-membre)) — avec son calendrier d'il y a un mois jusqu'à dans un an :
ce qu'il fréquente, organise et suit, moins ce qu'il masque. `ICalendar::ofOccurrences($occurrences, $stamp, $product,
$name)` écrit chaque occurrence en un `VEVENT` à ses propres heures, sans la règle de son événement, son `UID` étant
celui de l'occurrence — `Occurrence::id()`, l'identifiant de l'événement et le début en UTC —, son `URL` la page de cette
date quand elle en a une : une date d'une série hebdomadaire est autonome dans le calendrier du membre.

Un jeton qui n'a pas cette forme ne correspond à aucune route ; un jeton mal formé, inconnu ou révoqué reçoit
`404 Not Found` avec un corps vide, comme toute adresse qui n'existe pas. Chaque réponse porte
`Cache-Control: private, no-cache`, `X-Robots-Tag: noindex, nofollow` et `Referrer-Policy: no-referrer` : aucun cache
partagé ne la garde, aucun robot ne l'indexe, et aucune page vers laquelle elle pointe n'apprend son adresse. Elle porte
un `ETag` de ses occurrences, des versions de leurs événements et du nom ; un client qui le renvoie reçoit
`304 Not Modified`.

Le calendrier d'une page est le composant `PhilCalendar:Calendar` ([views.md](views.md)) ; il n'y a pas de flux JSON.
