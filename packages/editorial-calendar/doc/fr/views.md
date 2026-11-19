<!-- phil-dev source-sha256=3c4d0406610fd77b2507742634761bd34e58f1514cac1f02e62795373e50db41 -->
# Vues

`CalendarView` montre un calendrier entre deux moments. Chaque vue donne une `Period` : les moments UTC où elle
commence, inclus, et se termine, exclu, ses entrées dans leur ordre et, pour un jour, une semaine ou un mois, les
mêmes entrées jour par jour.

```php
$view = new CalendarView($transitions);
$zone = new DateTimeZone('America/Toronto');

$view->day($calendar, $date, $zone);
$view->week($calendar, $date, $zone);      // from Monday
$view->week($calendar, $date, $zone, 7);   // from Sunday
$view->month($calendar, $date, $zone);
$view->list($calendar, $from, $to);        // entries only, no days
```

## Les jours et les fuseaux horaires

Un jour, une semaine ou un mois est découpé dans le fuseau horaire donné : chaque `Day` contient sa date locale
(`Y-m-d`) et les moments UTC où il commence et se termine, à 23 ou 25 heures d'écart lors d'un changement d'heure. Une
semaine commence le jour numéroté 1 pour le lundi, comme l'ISO 8601 les numérote, jusqu'à 7 pour le dimanche ; tout
autre nombre lance `InvalidItem`. Une liste se termine après son début.

## Les entrées

Une `Entry` est une ligne à un moment UTC :

| `kind` | Ce qu'il montre |
|---|---|
| `EntryKind::Batch` | Un lot, avec tous ses éléments qui ne sont pas retirés, comme une seule entrée |
| `EntryKind::Item` | Un élément prévu seul, avec un moment |
| `EntryKind::Transition` | Une transition prévue ailleurs, lue par le `TransitionReader` |

Les entrées sont ordonnées par moment, puis lots, éléments et transitions, puis par `key` : l'identifiant de l'élément
ou du lot, ou la ressource et la transition. Les éléments retirés sont écartés ; les éléments publiés restent, au moment
où ils ont été publiés. Les idées et brouillons sans moment ne sont pas dans le temps :
`EditorialCalendar::unscheduled()` les liste, et `manualBatches()` liste les lots appliqués à la main.

## Les filtres

Un `Filter` garde les éléments de certains auteurs, de certaines sections ou de certains états ; chaque vue, `list()`
comprise, et `EditorialCalendar::unscheduled()` en prennent un comme dernier argument. Un critère laissé vide accepte
tous les éléments, et un élément est montré quand il passe tous les critères : `byAuthors()`, `bySections()` et
`byStates()` remplacent chacun leur propre critère et gardent les autres.

```php
use Phil\EditorialCalendar\Filter;

$filter = Filter::all()
    ->byAuthors(new AuthorId('user:7'))
    ->bySections('news', 'events')
    ->byStates(ItemState::Ready, ItemState::Scheduled);

$view->week($calendar, $date, $zone, filter: $filter);
```

Un élément sans section ne passe aucun critère de section. Un lot est montré quand l'un de ses éléments passe, avec
seulement les éléments qui passent. Les transitions prévues ailleurs n'ont ni auteur, ni section, ni état : un filtre
qui n'est pas vide les écarte, et `Filter::all()`, la valeur par défaut, les garde.

## Les transitions prévues ailleurs

Un site planifie plus que des publications : une page dépubliée à la fin d'une campagne, des inscriptions ouvertes à
une date. Le calendrier les lit par son port `TransitionReader`, dont `between($from, $to)` donne des valeurs
`PlannedTransition` — une `ResourceReference`, le nom de la transition et son moment —, et les montre à côté de ses
éléments. L'application implémente le port au-dessus de son planificateur ; `NoTransitions`, la valeur par défaut, ne
montre que les éléments du calendrier. Une transition que le lecteur donne hors de l'intervalle est écartée.
