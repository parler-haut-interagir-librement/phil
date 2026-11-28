<!-- phil-dev source-sha256=ac5d8b942777720b253e6923335fe35561cab69339b77cfde0438a980af79585 -->
# Twig

Ni Twig ni ses extensions supplémentaires n'écrivent une unité : `format_number` n'en connaît aucune. Le bundle ajoute
trois filtres.

```twig
{{ product.weight|format_measurement }}                                   {# 12,5 kg in fr_CA #}
{{ product.weight|format_measurement('long', 1) }}                        {# 12,5 kilogrammes #}
{{ product.weight|format_measurement('narrow', 0, 'en') }}                {# 13kg #}
{{ product.weight|measurement_in_channel('marketplace')|format_measurement }}
{{ person.height|preferred_units('person-height')|format_measurement }}   {# 5 ft, 10 in in en_US #}
```

| Filtre | Arguments | Donne |
|---|---|---|
| `format_measurement` | largeur (`long`, `short`, `narrow` ; `short` par défaut), nombre maximal de décimales (2), locale (celle de la requête) | La mesure écrite dans la locale, ou les parties d'une mesure répartie jointes comme la locale joint des unités ; rien pour `null`. |
| `measurement_in_channel` | canal | La mesure dans l'unité du canal pour sa famille, ou telle quelle quand le canal n'en a pas. |
| `preferred_units` | usage (`default`), région, décimales de la dernière partie | La mesure dans les unités que la région préfère pour l'usage, une liste à donner à `format_measurement`. |

La région de `preferred_units` est celle de la locale de la requête quand elle en nomme une, le monde (`001`) sinon.
Les chiffres sont arrondis comme des chaînes décimales, jamais par un nombre à virgule flottante, comme l'explique le
document « Channels, preferences and formatting » de `phil/measurement`.
