<!-- phil-dev source-sha256=724ee9fca0ae329786b0bc93e6377db78aac1d56abf7d341c7a0a5d36d8f409c -->
# Heures

| Type | Est | Se lit à partir de |
|---|---|---|
| `TimeOfDay` | Une heure à la minute, de `00:00` à `24:00` | `9`, `9h`, `9h30`, `09:30`, `24:00` |
| `TimeRange` | Quand un lieu ouvre et ferme | `09:00-17:00`, `9h–17h`, `10 to 24` |
| `DayHours` | Les plages d'un jour, en ordre et séparées ; aucune signifie fermé | `09:00-12:00, 13:00-17:00` |
| `SpecialHours` | Des heures qui remplacent celles de la semaine d'un jour à un autre, avec un libellé | voyez [la forme texte](text-schema-storage.md) |
| `OpeningHours` | La semaine, les heures spéciales, le fuseau horaire | |

**Une plage qui ferme avant d'ouvrir ferme le lendemain** : `18:00-02:00` est un vendredi soir qui se termine le
samedi à 2 h. Seule la dernière plage d'un jour peut le faire. Les plages qui se chevauchent sont refusées, celles qui
se touchent ne le sont pas. `24:00` ferme à la fin du jour ; aucune plage n'ouvre à cette heure.

**Les heures spéciales** couvrent des jours du calendrier, `Y-m-d`, d'un jour à un autre ; sans heures, elles ferment
le lieu. Les premières heures spéciales qui couvrent un jour l'emportent : un jour à l'intérieur d'une période se donne
donc avant elle.

**Le fuseau horaire** est un identifiant IANA, `UTC` par défaut : les heures sont celles de l'horloge du lieu, quel
que soit le fuseau horaire du moment demandé.

```php
OpeningHours::of(
    week: [1 => HoursText::day('09:00-17:00'), 6 => HoursText::day('10:00-16:00')],   // ISO day numbers; missing days are closed
    specials: [SpecialHours::of('2026-12-24', hours: HoursText::day('09:00-12:00'), label: 'Christmas Eve'), SpecialHours::of('2026-12-24', '2027-01-02')],
    timezone: 'America/Toronto',
);
```

`InvalidOpeningHours` est lancée pour une heure, une plage, une période, un libellé de plus de 100 caractères ou un
fuseau horaire impossibles.
