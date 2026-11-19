<!-- phil-dev source-sha256=2d55dace031ae2e19a5f8ee9cbdff8a81c8c7ce102e91229755205e92c902c4a -->
# Vues

Les vues du calendrier sont mises en page en PHP, sous `Application\View`, pour qu'un gabarit ne fasse que les dessiner :
la grille d'un mois, une semaine ou un jour par heure (partagés entre des organisateurs, des lieux ou des étiquettes
sur demande), une liste groupée par les jours que couvrent les éléments, et des affiches. Elles ne savent rien du HTML ;
le composant `PhilCalendar:Calendar` les dessine dans une page ([plus bas](#sur-une-page)).

## Période

`Period::of(CalendarView $view, $date, DateTimeZone $zone, int $firstWeekday = 1)` est ce qu'une vue montre, à partir
d'une date qu'elle contient ; `from` est inclus et `until` ne l'est pas, tous deux des minuits dans le fuseau horaire.

| Vue | `from` – `until` | `previous()`, `next()` |
|---|---|---|
| `Month` | Les semaines qui contiennent le mois : cinq ou six | Un mois, en gardant le jour ou le dernier d'un mois plus court |
| `Week` | La semaine qui contient la date | Sept jours |
| `Day` | Le jour | Un jour |
| `List`, `Posters` | Le mois | Un mois |

`firstWeekday` suit la norme ISO 8601 : 1 pour le lundi, 7 pour le dimanche. `in($view)` donne la même date dans une
autre vue ; `days()` énumère les minuits de la période ; `holds($day)` dit si un jour est dans le mois dont parle la
vue, pour les jours de la grille qui appartiennent aux mois voisins.

## Éléments

Un `Item` est ce qu'une vue montre : une occurrence d'un événement, ou une date de l'almanach : `id`, `title`, `start`,
`end`, `allDay`, `source` (`event` ou `almanac`), `url`, `tags`, `hosts` sous la forme `kind:reference`, `venue`,
`image`, `description`, `cancelled`. Les journées entières finissent au minuit de leur dernier jour ; une heure finit à
son instant. `firstDay()`, `lastDay()` (une heure qui finit à minuit finit la veille) et `spansDays()` ;
`firstDayNumber()` et `lastDayNumber()` donnent ces jours comme les compte `Period::dayNumber()`, en jours depuis le
1970-01-01.

| Source | Éléments |
|---|---|
| `EventItems` | Les occurrences du calendrier, les heures ramenées au fuseau horaire de la période, les journées entières gardées à leurs dates ; les organisateurs de chaque événement lus une seule fois |
| `AlmanacItems` | Les dates d'un `Phil\Almanac\Almanac`, avec `phil/almanac` ; elles ne mènent nulle part. Le bundle lui donne l'almanach sous forme de fermeture, demandée à chaque période, pour qu'un *worker* de longue durée montre les dates stockées depuis ; une période qui contient une année que l'almanach ne sait pas calculer (Pâques avant 1583) ne contient aucun élément de l'almanach |

`Items::of(Period, ?array $tags)` rassemble chaque `ItemSource`, par début (les journées entières d'abord), puis par
titre, en gardant ceux qui portent l'une des étiquettes lorsqu'on en donne.

## Le mois

`MonthGrid::of(Period, $items, int $lanes = 3)` donne des `MonthWeek` : leurs sept `days`, leurs `bars` et, par colonne,
les éléments qui n'ont pas trouvé place, `hidden`. Une `Bar` est un élément sur les jours qu'il couvre dans la
semaine (`column`, `span`, `lane`, `continuesBefore`, `continuesAfter`) : coupée à la fin d'une semaine, chacune dans la
première voie libre, la plus hâtive et la plus longue d'abord, les journées entières avant les heures. Au-delà des
voies montrées, un élément compte parmi les éléments cachés de chacun de ses jours : le « +2 de plus » d'un gabarit.
`lane($n)` donne les barres d'une voie, `lanes()` combien de voies la semaine utilise. Les jours de chaque élément sont
comptés une fois et l'élément n'est donné qu'aux semaines qu'il couvre : la mise en page croît avec les éléments, non
avec les éléments multipliés par les semaines.

## La semaine et le jour

`TimeGrid::of(Period, $items, ?Resources $resources, int $firstHour = 0, int $lastHour = 24)` donne une `TimeColumn` par
jour (par jour et par ressource lorsque les jours sont partagés), avec ses éléments de journée entière et ses
`TimedBlock` : `top` et `height` en pourcentage des heures montrées, `lane` sur `lanes` parmi les éléments qu'il
chevauche, `continuesBefore` et `continuesAfter`. Sans ressources, les journées entières sont des `Bar` sur la semaine,
`allDay`. Une heure hors des heures montrées est ramenée à leur bord ; un instant (une pleine lune) n'a pas de hauteur.

```php
$grid = TimeGrid::of($period, $items, Resources::hosts(['shop:7' => 'La boutique', 'club:2' => 'Le club'], 'Autres'));
```

`Resources::hosts()`, `venues()` et `tags()` prennent les colonnes par clé avec leurs libellés, et le libellé d'une
colonne pour les éléments qui n'appartiennent à aucune ; sans lui, ceux-ci ne sont pas montrés.

## La liste et les affiches

`Agenda::of($items)` groupe les éléments par les jours qu'ils couvrent, en `AgendaGroup` avec leur premier et leur
dernier jour (`first` et `last`) : par premier jour, puis par dernier jour, le plus court d'abord ; dans un groupe, les
journées entières d'abord, puis par heure et par titre. `items()` les donne tous une seule fois, dans cet ordre : les
affiches.

`DaySpan::label($first, $last, $locale, ?int $year)` écrit les jours d'un groupe :

| Jours | Français | Anglais |
|---|---|---|
| un | mardi 1er septembre | Tuesday, September 1 |
| deux | 1er et 2 septembre | September 1 and 2 |
| trois ou plus | 2 au 4 septembre | September 2–4 |
| à cheval sur des mois | 30 août au 2 septembre | August 30 – September 2 |
| une autre année | 15 et 16 mars 2027 | March 15 and 16, 2027 |

L'année s'écrit lorsque les jours ne sont pas tous dans l'année de référence (celle de la période), après le dernier
jour, et après le premier aussi lorsque les deux sont dans des années différentes. Une autre langue écrit chaque jour
comme le font les motifs d'ICU pour elle, reliés par un tiret : *1. September – 2. September*.

## Sur une page

`CalendarViews` est le service qu'une page utilise : `period($view, $date)` dans le fuseau horaire du calendrier
(`default_timezone`) et avec ses semaines (`first_weekday`), `zone()`, et `month()`, `time()` et `agenda()` d'une
période, filtrés par étiquettes, du calendrier propre à un membre lorsqu'on en donne un, `time()` partageant les jours entre `hosts`,
`venues` ou `tags`. `resources()` donne ces colonnes : les organisateurs et les étiquettes que portent les éléments,
chaque lieu du calendrier. L'application nomme ses organisateurs en implémentant `HostLabels` ; un organisateur
qu'elle ne nomme pas s'affiche sous la forme `kind:reference`.

`Items` rassemble les services étiquetés `phil_calendar.item_source` : `EventItems`, et `AlmanacItems` lorsque
`phil/almanac-bundle` enregistre son almanach. `EventItems` est étiqueté avant `AlmanacItems`, pour que, de deux
éléments qui commencent ensemble avec le même titre, l'événement du calendrier vienne en premier. Une application ajoute
sa propre source en étiquetant une `ItemSource`, `before` ou `after` nommant la source avant ou après laquelle elle va. Pour un membre, `Items::of()` interroge `EventItems::itemsOf()`, les
occurrences de son propre calendrier, et les autres sources telles quelles.

### Le composant

Avec Symfony UX Live Component, `<twig:PhilCalendar:Calendar />` est le calendrier d'une page :

| Propriété | Valeur par défaut | |
|---|---|---|
| `view` | `month` | `month`, `week`, `day`, `list` ou `posters` ; dans l'adresse |
| `date` | aujourd'hui | La date que contient la période, `Y-m-d`, dans le fuseau horaire du calendrier ; dans l'adresse |
| `tags` | `[]` | Seulement les éléments qui portent l'une de ces étiquettes ; dans l'adresse |
| `views` | les cinq | Les vues offertes, dans leur ordre ; une vue non offerte s'affiche comme la première |
| `shared` | aucun | `hosts`, `venues` ou `tags` : la semaine et le jour partagés entre eux |
| `firstHour`, `lastHour` | `0`, `24` | Les heures que montrent la semaine et le jour |
| `lanes` | `3` | Les voies que montre une semaine du mois avant son « de plus » |
| `member` | aucun | `kind:reference` : le calendrier propre à ce membre au lieu de tout le calendrier |

```twig
<twig:PhilCalendar:Calendar view="week" :views="['week', 'day', 'list']" shared="venues" :firstHour="8" :lastHour="23" />
```

Avec un `member`, le composant montre le calendrier propre à ce membre — ce auquel il participe, ce qu'il organise et ce
qu'il suit, moins ce qu'il masque ([Membres](members.md#le-calendrier-dun-membre)) — ; les autres sources, les dates de
l'almanach parmi elles, restent telles quelles. La page le fixe d'après la personne connectée ; il n'est pas modifiable,
et Live Component refuse une requête qui le change. Une valeur qui ne nomme aucun membre est une `InvalidEvent`.

```twig
<twig:PhilCalendar:Calendar view="list" member="member:{{ app.user.id }}" />
```

Lorsque le [budget](events.md#le-budget-dune-requête) de la requête laisse de côté des événements de la période, une
note le dit sous la vue, `calendar.cut`, dans un paragraphe `role="status"`, `phil-calendar__cut`.

Sa barre d'outils passe à la période précédente et à la suivante, revient à aujourd'hui, et change de vue sans
recharger la page : les actions `previous`, `next`, `today` et `show`. **Chaque bouton est un lien** vers la même
période dans l'adresse, `?view=week&date=2026-09-15` : un navigateur sans JavaScript, un rechargement, un lien partagé
montrent la même période. Une vue inconnue, une date illisible ou une date à plus de `CalendarComponent::YEARS` (100)
ans d'aujourd'hui se replient sur la première vue offerte et sur aujourd'hui : la date dans l'adresse, n'importe qui
peut l'écrire.

Les gabarits sont `templates/components/Calendar.html.twig` et `templates/calendar/_toolbar`, `_month`, `_time`,
`_list`, `_posters` et `_item`, dans l'espace de noms `@PhilCalendar`, qu'une application remplace comme ceux de
n'importe quel bundle. Ils dessinent les recettes du kit PHIL UX (`Popover`, `Badge`, `Card`, `Button`, `ButtonGroup` et
`Empty`), nommées par `phil_calendar_kit()` ([installation](installation.md)), et des classes bien à eux,
`phil-calendar__*`. Un élément est le déclencheur d'une bulle qui ouvre ses détails (quand, où, sa description, ses
étiquettes et sa page), sur l'API popover du navigateur ; un jour du mois ouvre de la même façon ce qui n'a pas trouvé
place. Les liens de la barre d'outils sont la recette Button dessinée comme des liens, en deux groupes de boutons ; une
affiche est une carte, son titre le lien qui la couvre ; une période sans éléments est un état vide.

### Accessibilité

Chaque jour du mois est un lien qui nomme la date complète pour les lecteurs d'écran et ouvre le jour ; la rangée des
jours de la semaine leur est cachée. Le contrôleur Stimulus `calendar` fait des jours un seul arrêt de l'ordre de
tabulation : les flèches se déplacent d'un jour ou d'une semaine (en miroir de droite à gauche), Début et Fin vont aux
extrémités de la semaine, Page précédente et Page suivante, ou une flèche au-delà du premier ou du dernier jour, vont
à la période d'avant ou d'après ; une touche avec Alt, Ctrl ou Meta est laissée au navigateur. Aujourd'hui est
`aria-current="date"`, la vue montrée `aria-current="true"` ; le titre est annoncé quand la période change ; les
flèches de la barre d'outils et un élément annulé ont un texte pour les lecteurs d'écran. Sans le contrôleur, chaque
jour, chaque lien et chaque bouton est dans l'ordre de tabulation.

Les dates s'écrivent dans le fuseau horaire du calendrier, quel que soit celui de Twig : les gabarits les formatent avec
`|date(…, false)`.

### L'apparence

`assets/styles/calendar.css` est écrite sur les jetons de PHIL UX, avec une valeur propre à chacun lorsque PHIL UX n'est
pas chargé, dans la couche `phil.components` : les styles d'une application l'emportent. Son texte réservé aux lecteurs
d'écran, `phil-calendar__hidden`, n'a besoin d'aucun utilitaire d'une autre feuille de style. `--phil-calendar-event` et
`--phil-calendar-almanac` colorent les éléments selon leur source, `--phil-calendar-hour-height` fixe la hauteur d'une
heure.

### Mots

| Twig | Donne |
|---|---|
| `calendar_days(first, last, year)` | `DaySpan::label()` dans la langue de la requête |
| `period\|calendar_title` | *septembre 2026*, les jours d'une semaine, un jour |
| `moment\|calendar_format(pattern)` | Un moment écrit avec un motif ICU : `EEE`, `HH:mm` |
| `moment\|calendar_time`, `hour\|calendar_hour` | Une heure, une heure juste, comme la langue les écrit en abrégé |
| `calendar_link(view, date, tags)` | L'adresse d'une vue et d'une date, pour les liens |

Les mots sont écrits par ICU avec un formateur par langue, style, fuseau horaire et motif, construit une fois et gardé
par l'extension : un mois de centaines d'éléments n'en formate qu'avec quelques-uns. Elle garde 64 formateurs au plus
et les reconstruit au-delà, pour que des langues et des fuseaux horaires tirés des requêtes ne fassent pas croître la
mémoire d'un *worker* qui vit à travers les requêtes.

## L'almanach en événements

`AlmanacImport::import($year, ?array $tags)` fait des dates de l'almanach d'une année des événements du calendrier,
pour celles qu'une application veut modifier, décrire ou faire organiser comme n'importe quelle autre : chacune est un
événement d'une journée entière (une phase, une saison ou une éclipse à son jour), organisé par `almanac:<key>`, créé
une seule fois, peu importe combien de fois l'année est importée. Elle retourne combien elle en a créé et combien
avaient déjà le leur. `phil:calendar:import-almanac 2027 --tag=holiday` le fait depuis la console.
