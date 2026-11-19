<!-- phil-dev source-sha256=d15f67d32f669ff1cb14a3200f132086957d33d4915a8927aeaf1a9c24044566 -->
# Installation

```sh
composer require phil/editorial-calendar
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec `ext-mbstring`, et `phil/resource`, dont
`ResourceReference` nomme le contenu qu'un élément publie. Elle n'a aucune autre dépendance à l'exécution.

Le calendrier et ses éléments sont des valeurs immuables que l'application stocke comme elle l'entend ;
`CalendarView`, `Review` et `ICalendarWriter` ne gardent aucun état entre les appels, donc une instance de chacun sert
chaque requête et chaque worker.

```yaml
# config/services.yaml
services:
    Phil\EditorialCalendar\View\CalendarView:
        arguments:
            $transitions: '@App\Calendar\SchedulerTransitions'   # an implementation of TransitionReader
    Phil\EditorialCalendar\Review\Review:
        arguments:
            $rules: !service { class: Phil\EditorialCalendar\Review\ReviewRules, arguments: [3600, 172800] }
    Phil\EditorialCalendar\ICalendar\ICalendarWriter:
        arguments: ['example.org']
```
