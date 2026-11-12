<!-- phil-dev source-sha256=e5aa3a8cd0348b8f60b81e67bc38c8e2604e1c6bd8b6b33874e2385fd38a3326 -->
# Configuration

```yaml
phil_almanac:
    timezone: America/Toronto        # the dates are computed in it
    database: false                  # true: also read phil_almanac_date
    dates:
        thanksgiving:
            title: Action de grâce
            rule: 2nd monday of october
            tags: [holiday]
        mardi-gras:
            title: Mardi gras
            rule: easter - 47 days
        oktoberfest:
            title: Oktoberfest
            rule: saturday on or after 09-15
            until: sunday on or after 10-03
        engineers-week:
            title: Semaine du génie
            rule: monday on or before 02-22
            days: 7
        full-moon:
            title: Pleine lune
            rule: full moon
            all_day: false           # shown at its instant
    eclipses:                        # declares solar-eclipse and lunar-eclipse
        enabled: true
        solar_title: Éclipse solaire
        lunar_title: Éclipse lunaire
        tags: [eclipse]
    places:                          # for sun_times()
        montreal: { latitude: 45.5017, longitude: -73.5673 }
        paris: { latitude: 48.8566, longitude: 2.3522, timezone: Europe/Paris }
```

| Clé | Valeur par défaut | |
|---|---|---|
| `timezone` | `UTC` | Un fuseau horaire IANA |
| `database` | `false` | Lit aussi les dates stockées, qui remplacent les dates configurées de même clé ; exige DoctrineBundle |
| `dates.<key>.title` | obligatoire | |
| `dates.<key>.rule` | obligatoire | Le début : toute règle de `phil/almanac` |
| `dates.<key>.until` | aucune | La fin : la première date de cette règle à partir du début, inclusivement |
| `dates.<key>.days` | `1` | Le nombre de jours, de 1 à 366, sans `until` |
| `dates.<key>.all_day` | `true` | `false` seulement pour une phase, une saison ou une éclipse, affichée à son instant |
| `dates.<key>.tags` | `[]` | Des mots sur lesquels filtrer |
| `eclipses.enabled` | `false` | Déclare `solar-eclipse` et `lunar-eclipse`, à l'instant de leur maximum ; `eclipses: true` suffit |
| `eclipses.solar_title`, `lunar_title` | `Solar eclipse`, `Lunar eclipse` | Leurs titres |
| `eclipses.tags` | `[eclipse]` | Leurs étiquettes |
| `places.<name>.latitude` | obligatoire | Degrés décimaux, de −90 à 90, positifs au nord |
| `places.<name>.longitude` | obligatoire | Degrés décimaux, de −180 à 180, positifs à l'est |
| `places.<name>.timezone` | `timezone` | Le fuseau horaire IANA dans lequel se lisent son lever et son coucher |

Une date de `dates` dont la clé est `solar-eclipse` ou `lunar-eclipse` remplace celle qui est déclarée :
`rule: total solar eclipse` ne garde que les éclipses totales. Les clés sont gardées telles qu'elles sont écrites,
tirets compris.

La clé est celle de la date : une lettre minuscule ou un chiffre, puis jusqu'à 99 lettres minuscules, chiffres, `.`, `-`
ou `_`. **Chaque date est lue à la construction du conteneur** : une règle que l'almanach ne peut pas lire, un `until`
avec `days` ou une journée affichée à son heure arrêtent la construction avec un message qui nomme la date —
`phil_almanac.dates.noel: "every blue moon" is not a rule the almanac reads.` Les règles sont énumérées dans le
`doc/rules.md` de `phil/almanac`.
