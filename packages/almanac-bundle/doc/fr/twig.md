<!-- phil-dev source-sha256=8d9d8e835bf1e40d5acbdfe54d1ea24d4d9b31c0f976b8e10977bc56a1269361 -->
# Twig

| Fonction | Donne |
|---|---|
| `almanac_dates(from, until, tags)` | Les `ComputedDate` qui touchent la période, sa fin exclue, triées par début puis par titre ; `tags` garde celles qui portent l'une de ces étiquettes |
| `sun_times(place, day = 'today')` | Le `SolarDay` d'un lieu (un nom de `phil_almanac.places`, ou un `Place`) pour un jour |
| `almanac_eclipses(from, until, body = null)` | Les `SolarEclipse` et les `LunarEclipse` dont le maximum tombe dans la période, sa fin exclue, dans l'ordre ; `'solar'` ou `'lunar'` en garde un seul astre |

`from` et `until` sont des dates — tout `DateTimeInterface`, y compris un `DateTime` modifiable —, prises comme les
instants qu'elles sont, ou un texte que `DateTimeImmutable` lit dans le fuseau horaire de l'almanach : `'2026-10-01'`
correspond à minuit dans ce fuseau, quel que soit le fuseau horaire par défaut de PHP. Chaque date a sa `definition` —
`key`, `title`, `tags` — et son `span` — `start`, `end`, `allDay`, `days()`.

```twig
<ul>
    {% for date in almanac_dates('now', '+1 month', ['holiday']) %}
        <li>{{ date.definition.title }}, {{ date.span.start|date('j F') }}</li>
    {% endfor %}
</ul>
```

## Le Soleil d'un lieu

`sun_times()` lit un texte dans le fuseau horaire du lieu (`'today'` est le jour courant du lieu) et une date comme
l'instant qu'elle est. Son `SolarDay` a `noon`, `sunrise()`, `sunset()`, `dawn()` et `dusk()` (le crépuscule civil par
défaut, ou `Twilight::Nautical` et `Twilight::Astronomical`), et `dayLength()` en secondes. Sous le Soleil de minuit ou
dans la nuit polaire, `sunrise()` et `sunset()` sont null : un gabarit les vérifie. Chaque instant est dans le fuseau
horaire du lieu ; `date('H:i', false)` le conserve.

```twig
{% set day = sun_times('montreal') %}
{% if day.sunrise %}
    <p>Sunrise {{ day.sunrise|date('H:i', false) }}, sunset {{ day.sunset|date('H:i', false) }}.</p>
{% endif %}
```

Un lieu que la configuration n'a pas est une `InvalidArgumentException` qui énumère ceux qu'elle a.

## Éclipses

```twig
{% for eclipse in almanac_eclipses('now', '+2 years') %}
    <li>{{ eclipse.kind.value }} {{ eclipse.at|date('j F Y, H:i') }}</li>
{% endfor %}
```

Une `LunarEclipse` a aussi ses phases : `penumbral`, `partial` et `total`, chacune un début et une fin, ou null.
Déclarées avec `eclipses: true`, ce sont aussi des dates de l'almanach, parmi les autres de `almanac_dates()`.

`phil/calendar-bundle` affiche les mêmes dates parmi ses événements.
