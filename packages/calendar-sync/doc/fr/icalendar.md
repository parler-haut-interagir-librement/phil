<!-- phil-dev source-sha256=705b587da8a02db4591ca5bdd8c69cb1ccf624d42b933dfd138078f41dc3ae33 -->
# Le lecteur iCalendar

`ICalendar\ICalendarReader::busy()` donne les plages occupées d'un texte iCalendar (RFC 5545) qui chevauchent une
fenêtre. Les adaptateurs `ics` et `caldav` lisent avec lui ; une application peut lire un calendrier qu'elle détient
déjà.

## Ce qui est lu

| Composant | Ce qui donne une plage |
|---|---|
| `VEVENT` | Son début, et sa fin, sa durée, ou un jour pour une journée entière ; une heure sans l'une ni l'autre n'en donne aucune |
| `VEVENT` avec `RRULE` | Chaque occurrence de la règle qui chevauche la fenêtre, lue par `phil/recurrence` |
| `RDATE`, `EXDATE` | Des occurrences ajoutées et retirées, de la durée de l'événement ; une période de `RDATE` par son début |
| `RECURRENCE-ID` | Une occurrence modifiée : lue seule, et retirée de sa règle |
| `VFREEBUSY` | Chaque période `FREEBUSY`, un début et une fin ou une durée, sauf celles qui sont `FREE` |

Un événement annulé (`STATUS:CANCELLED`) ou transparent (`TRANSP:TRANSPARENT`) ne donne rien. Un événement provisoire
(`STATUS:TENTATIVE`) et une période `BUSY-TENTATIVE` donnent des plages provisoires.

Seules ces propriétés sont lues : `DTSTART`, `DTEND`, `DURATION`, `RRULE`, `RDATE`, `EXDATE`, `RECURRENCE-ID`, `UID`,
`STATUS`, `TRANSP`, `FREEBUSY`. Les autres — le titre, la description, le lieu, les invités, les pièces jointes — sont
sautées sans être décodées, et les propriétés d'un composant à l'intérieur d'un événement, une alarme, ne sont pas lues.

## Les fuseaux horaires

- Une heure avec `Z` est en UTC.
- Une heure avec `TZID` est dans ce fuseau : un nom IANA, avec une barre oblique initiale retirée, ou un nom Windows
  comme Outlook les écrit, lu par ICU. Un nom que ni l'un ni l'autre ne connaît est lu dans le fuseau du calendrier. Les
  définitions `VTIMEZONE` ne sont pas lues.
- Une heure flottante, et une journée entière, est dans le fuseau du calendrier : celui de la connexion.

Les occurrences d'une règle gardent l'heure de la première sur l'horloge murale de son fuseau, à travers les changements
d'heure. La fin d'une occurrence est autant de jours et de secondes après son début que la fin de l'événement l'est
après le sien.

## Les bornes

| Borne | Valeur |
|---|---|
| Le texte | 10 Mio |
| Composants | 20 000, imbriqués sur 8 niveaux |
| Une propriété lue | 20 000 octets une fois dépliée |
| Événements répétés | 1 000, comptés avant qu'aucun soit déplié, et sur l'ensemble des `calendar-data` d'une réponse CalDAV |
| Dates ajoutées ou retirées, pour un événement | 10 000 |
| Plages dans la fenêtre | 10 000 |

## Ce qui est refusé

Un texte qui dépasse une borne est refusé avec `InvalidCalendarData`, tout comme un texte qui ne commence pas par
`BEGIN:VCALENDAR`, qui contient quelque chose après son dernier `END:VCALENDAR`, qui termine des composants dans le
désordre ou en laisse un ouvert, qui a une ligne sans deux-points ou un paramètre mal formé, un événement sans début,
une date qui n'est pas écrite `20261110` ou `20261110T190000` ou qui n'existe pas, une durée négative ou vide, ou une
règle que `phil/recurrence` ne lit pas — une règle horaire, un `BYWEEKNO`. Un calendrier est refusé en entier plutôt que
lu à moitié : à moitié lu, il laisserait libres des heures qui ne le sont pas.

Le lecteur est une cible de fuzzing : voyez les [tests](testing.md).
