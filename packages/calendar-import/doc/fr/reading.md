<!-- phil-dev source-sha256=9d97bf8d16001048198ffa2de8479986ec951929cb331fa72da475df205d1f69 -->
# Lire de l'iCalendar

`ICalendarReader::read($text, $zone)` lit les événements d'un texte iCalendar (RFC 5545) et donne un `ReadResult` :
les événements lus (`SourceEvent`), les événements refusés avec leur raison (`RejectedEvent`) et le nom du calendrier
(`X-WR-CALNAME`), s'il y en a un. Le lecteur ne garde rien entre deux lectures : une seule instance sert toutes les
requêtes d'un *worker*.

```php
use Phil\CalendarImport\ICalendar\ICalendarReader;

$result = (new ICalendarReader())->read($text, new DateTimeZone('America/Toronto'));

foreach ($result->events as $event) {
    // $event->uid, $event->start, $event->title, $event->rule…
}

foreach ($result->rejected as $rejected) {
    // $rejected->uid, $rejected->reason (a Rejection), $rejected->message
}
```

Le lecteur est strict : il suit la RFC 5545 là où elle est claire, et refuse ce qu'il ne lit pas plutôt que de
deviner. Un texte est refusé en entier, ou un seul événement est refusé ; rien n'est lu à moitié.

## Les lignes

- Un saut de ligne — CRLF, un LF seul ou un CR seul — suivi d'une espace ou d'une tabulation joint la ligne suivante
  à la sienne (dépliage). Une marque d'ordre des octets au début est ignorée. Le texte est vérifié comme de l'UTF-8
  une fois déplié, de sorte qu'un caractère plié au milieu de ses octets est lu en entier.
- Chaque ligne est un nom, des paramètres et une valeur : `DTSTART;TZID=America/Toronto:20261110T190000`. La valeur
  d'un paramètre est entre guillemets ou nue ; sa forme entre guillemets ne contient ni guillemet ni caractère de
  contrôle. Les valeurs de paramètres sont décodées comme l'écrit la RFC 6868 (`^n`, `^'`, `^^`).
- Une valeur de type TEXT est déséchappée : `\n` et `\N` sont des sauts de ligne, `\\`, `\;` et `\,` les caractères
  qu'ils échappent ; une autre barre oblique inverse reste telle qu'écrite. Le résultat est rogné, et un texte vide
  n'est aucun texte.
- Chaque motif est possessif : le temps passé sur une ligne croît avec sa longueur, jamais plus vite.

## Les composants

Un texte est un ou plusieurs `VCALENDAR`, et rien en dehors d'eux. `BEGIN` et `END` doivent s'imbriquer. Dans un
calendrier, le lecteur lit chaque `VEVENT` et chaque `VTIMEZONE` ; tout autre composant (`VTODO`, `VJOURNAL`,
`VALARM`, `VFREEBUSY`, un composant `X-`) est ignoré, mais il compte dans les bornes.

`X-WR-TIMEZONE`, quand il nomme un fuseau IANA, est le fuseau des heures écrites sans fuseau. Sinon, c'est le fuseau
passé à `read()` ; UTC par défaut.

## Les propriétés d'un événement

| Propriété | Lue comme |
|---|---|
| `UID` | obligatoire ; 255 octets au plus, sur une seule ligne |
| `SEQUENCE` | un nombre entier, neuf chiffres au plus ; 0 en son absence |
| `LAST-MODIFIED`, `DTSTAMP` | une heure en UTC, `20261110T190000Z` |
| `DTSTART` | obligatoire : un jour (`20261110`, avec `VALUE=DATE` ou seul), une heure en UTC (`…Z`), une heure dans son `TZID`, ou une heure flottante |
| `DTEND` ou `DURATION` | l'un des deux au plus ; la fin est un jour quand le début en est un, et jamais avant le début |
| `SUMMARY`, `DESCRIPTION`, `LOCATION` | du texte brut |
| `URL` | lue par `phil/url` ; une adresse qu'il refuse est omise, et l'événement conservé |
| `STATUS` | `TENTATIVE`, `CONFIRMED` ou `CANCELLED` ; `CONFIRMED` en son absence |
| `CATEGORIES` | les étiquettes, chaque ligne lue, chaque étiquette conservée une seule fois ; 100 au plus |
| `RRULE` | lue par `RecurrenceRule::parse()` de `phil/recurrence`, dans le fuseau du début |
| `RDATE`, `EXDATE` | lues par `RecurrenceDates::parse()` de `phil/recurrence`, dans le fuseau de leur propre `TZID` |
| `RECURRENCE-ID` | l'occurrence qu'une instance modifiée remplace |
| `CLASS` | `PRIVATE` et `CONFIDENTIAL` font refuser l'événement |

Sans `DTEND` ni `DURATION`, un événement d'un jour dure ce jour-là, et un événement à une heure donnée ne dure aucun
temps. Une durée s'écrit `P1W`, `P1D`, `PT2H30M` ou `P1DT2H`, jamais négative, et un événement de jours entiers dure
des jours entiers.

Un événement annulé est lu, avec `STATUS:CANCELLED` : l'importateur le signale, et une application supprime ou marque
l'occurrence. Son brouillon le dit avec `isCancelled()`.

Les autres propriétés — `ATTENDEE`, `ORGANIZER`, `ATTACH`, `GEO`, les alarmes — ne sont pas lues. L'adresse d'un
participant n'atteint jamais la sortie de la bibliothèque.

### La clé d'un événement

Chaque événement a une clé : son UID, suivi, pour une occurrence modifiée, de `#` et de l'occurrence qu'elle
remplace — `20261110` pour un jour entier, `20261110T190000Z` en UTC autrement. Les instantanés, les différences et
les surcharges vont par clé.

### Les doublons

Deux événements de même clé, c'est un de trop. Celui dont le `SEQUENCE` est le plus élevé est gardé, puis le
`LAST-MODIFIED` le plus récent, puis le `DTSTAMP` le plus récent, puis le premier ; l'autre est signalé comme
`Rejection::DuplicateUid`.

## Les fuseaux horaires

Un `TZID` est résolu dans cet ordre :

1. un nom IANA que PHP connaît, en toute casse, avec ou sans le `/` initial d'un nom globalement unique ;
2. un nom Windows, comme Outlook les écrit (`Eastern Standard Time`), par ICU ;
3. le `VTIMEZONE` de ce `TZID` dans le texte, quand il est simple : son `X-LIC-LOCATION` est un nom IANA, ou le
   `TZID` se termine par un nom IANA (`/mozilla.org/20050126_1/America/Toronto`), ou toutes les observances qu'il
   contient ont le même décalage, lu comme un décalage fixe.

Les règles d'un `VTIMEZONE` ne sont jamais suivies pour fabriquer un fuseau. Un événement dans un fuseau qu'aucune de
ces voies ne résout est refusé seul avec `Rejection::UnknownTimeZone` ; les autres événements sont lus.

## Ce qui est refusé

### Le texte entier

`UnreadableCalendar` est lancée, et aucun événement n'est lu :

- `InvalidCalendar` — le texte n'est pas de l'UTF-8, ne contient aucun `VCALENDAR`, contient quelque chose en dehors
  d'un `VCALENDAR`, a des composants qui ne s'imbriquent pas, ou une ligne directement dans un `VCALENDAR` qui n'est
  pas un nom, des paramètres et une valeur (une telle ligne dans un événement fait refuser cet événement ; dans un
  `VTIMEZONE`, elle fait omettre cette définition) ;
- `LimitExceeded` — le texte dépasse l'une de ses `ReaderLimits` ; `$exception->limit` nomme la borne (une `Limit`).

### Un seul événement

L'événement est refusé avec une `Rejection`, et les autres sont lus :

| Rejection | Quand |
|---|---|
| `Malformed` | une ligne de l'événement est illisible, une propriété à valeur unique apparaît deux fois, ou `DTEND` et `DURATION` sont tous deux présents |
| `MissingUid` | aucun `UID`, ou un `UID` vide |
| `MissingStart` | aucun `DTSTART` |
| `InvalidDate` | une date écrite autrement, un jour qui n'existe pas, une année 0, une fin avant le début, un jour et une heure mélangés |
| `UnknownTimeZone` | un `TZID` que rien ne résout |
| `InvalidDuration` | une durée écrite autrement, ou des heures dans un événement de jours entiers |
| `InvalidRecurrence` | une règle ou des dates que `phil/recurrence` refuse, ou trop de dates |
| `InvalidValue` | un statut, une séquence, un UID ou des catégories hors de ce qu'ils permettent |
| `Private` | `CLASS:PRIVATE` ou `CLASS:CONFIDENTIAL` |
| `DuplicateUid` | un autre événement de même clé a été gardé |
| `Unsupported` | `RECURRENCE-ID;RANGE=THISANDFUTURE` |

## Les bornes

`ReaderLimits` borne le travail fait sur un texte par des nombres, jamais par le texte :

| Borne | Valeur par défaut | Maximum permis |
|---|---|---|
| `bytes`, du texte entier | 5 Mio | 64 Mio |
| `events` | 5 000 | 100 000 |
| `lineBytes`, d'une ligne dépliée | 16 Kio | 1 Mio |
| `depth`, `VCALENDAR` compris | 6 | 16 |
| `components`, de tout genre | 20 000 | 200 000 |

Une borne hors de sa plage lance `InvalidLimits`. Chaque borne est vérifiée à mesure que le texte est lu, et la lecture
s'arrête à la première dépassée.

## Du texte, jamais du HTML

Chaque texte que le lecteur donne est du texte brut, tel que le calendrier l'a écrit une fois déséchappé : rien n'est
lu comme du HTML, et rien n'est décodé davantage. L'application l'échappe là où elle l'affiche — Twig le fait par
défaut.
