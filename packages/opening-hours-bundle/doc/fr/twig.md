<!-- phil-dev source-sha256=5c3d542e0deab6f12b7ea1aea1e9d37062397412d32395337d43a0d812f22146 -->
# Twig

| Fonction | Donne |
|---|---|
| `opening_hours_open(hours, at = now)` | Si le lieu est ouvert ; `false` pour `null` |
| `opening_hours_next(hours, at = now)` | Quand il ouvre de nouveau, dans son fuseau horaire, ou `null` |
| `opening_hours_week(hours, locale = null)` | Des lignes avec `days`, `lundi – vendredi` ; `heading`, la même chose avec seulement sa première lettre en majuscule, `Lundi – vendredi`, `Monday – Friday` ; et `hours`, `09 h 00 – 17 h 00`, ou `null` quand il est fermé |
| `opening_hours_specials(hours, at = now, locale = null)` | Les heures spéciales pas encore terminées selon le calendrier du lieu, `at` étant lu dans son fuseau horaire : `period`, `label`, `hours` ou `null` quand il est fermé |
| `opening_hours_schema(hours)` | L'`openingHoursSpecification`, à mettre dans le JSON-LD d'un lieu |

Les jours, les dates et les heures sont écrits par ICU dans la langue de la requête, ou dans celle donnée ; la fin du
jour s'écrit minuit. Le filtre `capitalize` de Twig mettrait en minuscules toutes les lettres après la première,
`Monday – friday` : c'est le `heading` d'une ligne qu'il faut mettre en début de ligne. Les formateurs sont construits
une seule fois par langue et par style, puis gardés.

## Le gabarit de tableau

```twig
{% include '@PhilOpeningHours/table.html.twig' with {hours: shop.hours} %}
```

Un tableau de la semaine, et un autre des heures spéciales à venir, avec *Fermé* ou *Closed* là où le lieu l'est.
