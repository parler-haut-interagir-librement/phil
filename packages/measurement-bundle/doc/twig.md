# Twig

Neither Twig nor its extra extensions write a unit: `format_number` knows none. The bundle adds three filters.

```twig
{{ product.weight|format_measurement }}                                   {# 12,5 kg in fr_CA #}
{{ product.weight|format_measurement('long', 1) }}                        {# 12,5 kilogrammes #}
{{ product.weight|format_measurement('narrow', 0, 'en') }}                {# 13kg #}
{{ product.weight|measurement_in_channel('marketplace')|format_measurement }}
{{ person.height|preferred_units('person-height')|format_measurement }}   {# 5 ft, 10 in in en_US #}
```

| Filter | Arguments | Gives |
|---|---|---|
| `format_measurement` | width (`long`, `short`, `narrow`; `short` by default), maximum fraction digits (2), locale (the request's) | The measurement written in the locale, or the parts of a split one joined as the locale joins units; nothing for `null`. |
| `measurement_in_channel` | channel | The measurement in the channel's unit for its family, or as it is when the channel has none. |
| `preferred_units` | usage (`default`), region, fraction digits of the last part | The measurement in the units the region prefers for the usage, a list to give to `format_measurement`. |

The region of `preferred_units` is the one of the request's locale when it names one, the world (`001`) otherwise.
The digits are rounded as decimal strings, never through a float, as the "Channels, preferences and formatting"
document of `phil/measurement` explains.
