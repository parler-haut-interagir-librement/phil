<!-- phil-dev source-sha256=d41675945360d289f2bfa6c5f878f270335cb92ae2502f440699e8c17a4331a2 -->
# iCalendar

`ICalendarWriter` écrit les publications d'un calendrier entre deux moments sous forme de flux iCalendar (RFC 5545),
pour qu'une application de calendrier s'y abonne.

```php
$writer = new ICalendarWriter('example.org');

return new Response($writer->write($calendar, $from, $to, $now, 'Blog'), 200, [
    'Content-Type' => 'text/calendar; charset=utf-8',
]);
```

Chaque élément prévu seul et chaque lot forment un `VEVENT` :

| Propriété | Valeur |
|---|---|
| `UID` | `item-<id>@<domain>` ou `batch-<id>@<domain>`, stable d'un export à l'autre |
| `DTSTAMP` | Le moment donné à `write()`, en UTC |
| `DTSTART` | Le moment de l'élément ou du lot, en UTC |
| `SUMMARY` | Le titre de l'élément, ou le titre du lot et son nombre d'éléments |
| `DESCRIPTION` | Chaque élément : titre, langue, état et référence du contenu |
| `CATEGORIES` | L'état de l'élément, ou `batch` |
| `STATUS` | `CONFIRMED` quand chaque élément est planifié ou publié, `TENTATIVE` avant |

Les textes sont échappés — barres obliques inverses, points-virgules, virgules et sauts de ligne —, les lignes se
terminent par CRLF et sont repliées à 75 octets, jamais au milieu d'un caractère UTF-8. Les éléments retirés et les
transitions prévues ailleurs ne sont pas exportés. Le domaine, qui rend les identifiants uniques, doit être un nom
d'hôte ; toute autre valeur lance `InvalidItem`.

Une publication prévue a lieu une seule fois : aucun événement ne se répète, donc le rédacteur n'a besoin d'aucune
règle de récurrence.
