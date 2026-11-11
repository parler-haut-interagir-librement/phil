<!-- phil-dev source-sha256=02222044f9c12e30f8983202ef15a4df6d507bb10e26bc78742d7a96307f3fe8 -->
# Points d'extension

Ce qu'une application configure, étend ou redéfinit. Les heures sont celles de `phil/opening-hours`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `default_timezone` | Le fuseau horaire des nouvelles heures — voyez [Installation](installation.md) |

## Formulaires

| Point | Comment |
|---|---|
| Le fuseau horaire d'un champ | L'option `timezone` d'`OpeningHoursType`, et `with_timezone` pour laisser les gens le choisir |
| La langue des jours | Son option `locale` |
| Plus que ce que fait le formulaire | Une extension de type de formulaire de l'application sur `OpeningHoursType` |

Voyez [Formulaires](forms.md).

## Stockage et Twig

| Point | Comment |
|---|---|
| Où les heures sont gardées | Le type de colonne `phil_opening_hours`, du JSON par `OpeningHoursArray` — voyez [Doctrine](doctrine.md) |
| Ouvert maintenant, prochaine ouverture, la semaine | `opening_hours_open`, `opening_hours_next`, `opening_hours_week`, `opening_hours_specials` et `opening_hours_schema` — voyez [Twig](twig.md) |
| Le tableau des heures | `@PhilOpeningHours/table.html.twig`, redéfini dans `templates/bundles/PhilOpeningHoursBundle/table.html.twig` comme tout gabarit de bundle, ou remplacé par une mise en page de l'application qui repose sur `opening_hours_week` |
