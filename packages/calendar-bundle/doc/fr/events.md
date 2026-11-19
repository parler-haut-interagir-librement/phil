<!-- phil-dev source-sha256=797e04c34e0f48ffc2c9b80982dff4826766403e459e8f1b2fce5093e541a57c -->
# Événements

## `Event`

Un événement a un titre, de 200 caractères au plus ; une description, un lieu de 255 caractères au plus et une page
http ou https ; un début et une fin à l'heure locale de son fuseau horaire, gardés en UTC ; ou des journées entières,
de la première à la dernière.

```php
$event = Event::schedule('Soirée jeux', $start, $end, timezone: 'America/Toronto');
$event = Event::schedule('Festival', new DateTimeImmutable('2026-07-10'), new DateTimeImmutable('2026-07-12'), allDay: true);

$event->rename('Soirée jeux de société');
$event->describe('Apportez vos jeux.', 'Café X', 'https://example.com/jeux');
$event->reschedule($start, $end, allDay: false, timezone: 'Europe/Paris');
$event->repeat(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU'));  // null: it no longer repeats
$event->addDate($anotherStart);                                    // as RDATE
$event->except($occurrenceStart);                                  // as EXDATE
$event->tag(['jeux', 'soirée']);                                   // each once, trimmed
$event->cancel();                                                  // still shown, marked cancelled
```

Un événement qui finit avant de commencer, un fuseau horaire inconnu, un titre trop long ou une page d'un autre schéma
est une `InvalidEvent`. La page est lue par `phil/url` comme un navigateur la lit et gardée normalisée :
`HTTPS://Example.com:443/a` devient `https://example.com/a`. Une adresse sur une IP ou sur `localhost` est refusée, et
l'`InvalidUrl` qui en donne la raison est l'exception précédente. Une nouvelle règle efface les occurrences retirées ;
la même règle donnée de nouveau (une modification par le formulaire qui laisse l'horaire intact) les garde. Les
occurrences gardent l'heure de la première, à l'heure locale du fuseau horaire de l'événement : lors d'un changement
d'heure, 19 h reste 19 h.

`addDate($start)` ajoute une occurrence qui commence à un moment après le début de l'événement, de la durée de
l'événement, comme le fait `RDATE` : un événement de quelques dates n'a pas besoin de règle, et une règle gagne une date
qu'elle ne donne pas. Un événement d'une journée entière ajoute le jour où tombe le moment, dans son fuseau horaire.
Une date ajoutée est gardée quelle que soit la règle selon laquelle l'événement se répète, fusionnée dans l'ordre avec
ses occurrences, une seule fois même quand la règle la donne aussi ; `except()` la retire comme n'importe quelle
occurrence, et `removeDate()` la reprend. Une date que le nouveau début de l'événement dépasse est abandonnée. Un
événement a 1 000 dates ajoutées au plus (`Event::ADDED_DATES`, autant qu'une ligne `RDATE` en contient) ;
`addedDates()` les énumère dans le fuseau horaire de l'événement.

`occurrences($from, $through, $limit = 1000)` donne dans l'ordre les occurrences qui chevauchent une période : une
seule pour un événement qui ne se répète pas. Une occurrence d'une journée entière dure jusqu'à la fin de son dernier
jour. La règle est lue à partir de juste avant la période, non à partir de sa première occurrence : un mois d'un
événement qui se répète depuis trente ans coûte autant qu'un mois d'un événement tout neuf. `seriesEndsAt()` est le
moment où la dernière occurrence finit, `null` pour une règle sans fin : le dépôt trouve ainsi les événements d'une
période. Une règle qui finit à une date est relue à partir de cette date, sa dernière année en premier, de sorte
qu'enregistrer une longue série coûte ses dernières années. La fin de la série est celle de la règle ou celle de la
dernière date ajoutée, la plus tardive des deux. La récurrence et la fin de la série sont gardées avec ce dont elles ont
été tirées (la règle, les heures, le fuseau horaire, les occurrences retirées, les dates ajoutées) et relues dès que
l'un de ces éléments change : une modification qui replanifie et répète selon la même règle ne lit la série qu'une fois.

## Une page propre, et la capacité

`linkDate($occurrence, $url)` donne à l'occurrence qui commence à un moment une page propre (où ses billets se vendent),
lue et normalisée par `phil/url` comme l'est la page de l'événement ; null la retire. `urlOn($start)` est cette page,
ou celle de l'événement ; les vues lient chaque occurrence à celle-ci. Un événement donne une page propre à 500
occurrences au plus, et une nouvelle règle les retire avec les occurrences retirées. `dateLinks()` les énumère par le
début de leur occurrence.

`limitTo($capacity)` fixe combien de personnes peuvent participer à chaque occurrence, de 1 à 1 000 000, ou aucune
limite. Voir [Participants, propositions et calendriers de membres](members.md).

## `Occurrence`

Un événement, un début et une fin. `id()` est l'identifiant de l'événement et le début en UTC, `…@20261111T000000Z` :
le même d'une requête à l'autre.

## Lieux et images

Un `Venue` est l'endroit où les événements ont lieu : un lieu propre au calendrier (un bar, une salle sans page propre
sur le site) ou une fiche du répertoire de l'application, nommée par un genre et une référence comme le sont les
organisateurs, `listing()` sous la forme `kind:reference`. Il a un `name`, une `room`, une `address` et `coordinates()`,
la latitude et la longitude toutes les deux ou aucune ; `label()` est son nom suivi, après un tiret, de sa salle,
*Salle Le Royal — salle B*. `Event::placeAt()` fixe le lieu d'un événement (`location()` reste pour un lieu dit en
mots), et supprimer un lieu laisse ses événements sans lieu.

`Venues` crée et modifie des lieux à partir d'un `VenueDraft` (`VenueType` en est le formulaire), les supprime, les
énumère par nom, et `ofListing($kind, $reference, $name)` donne le lieu d'une fiche, créé à partir de son nom la
première fois : les lieux du répertoire deviennent ceux du calendrier sans être saisis deux fois. `VenueRepository` est
le port, `DoctrineVenueRepository` son adaptateur.

```php
$venue = $venues->ofListing('larp-site', '12', 'Terrain du Nord');
$draft->venue = $venue;
$draft->image = '/affiches/salon.jpg';
$calendar->schedule($draft);
```

L'`image()` d'un événement, son affiche, est une adresse `http` ou `https` ou un chemin à partir de la racine du site,
de 2048 caractères au plus, fixée par `illustrate()`. Un chemin qu'un navigateur lirait comme celui d'un autre
hôte (`//host/x.png`, `/\host/x.png`, ou toute barre oblique inverse) est refusé, par l'entité comme par la validation du
brouillon. Une adresse est lue et normalisée par `phil/url`, comme l'est la page. La vue des affiches l'affiche.

## Organisateurs et étiquettes

Un organisateur est celui qui tient l'événement : un genre et une référence propres à l'application, de 1 à 64 lettres,
chiffres, `_`, `.`, `:` ou `-` chacun : `association` et `42`, `shop` et `7`. Un événement a autant d'organisateurs
qu'on veut, un `EventHost` chacun, stockés à part : l'événement ne les charge jamais.

Les étiquettes sont des mots libres, de 100 caractères au plus chacun, gardés dans la ligne de l'événement. Le
calendrier filtre sur elles telles qu'elles sont écrites : `jeux` n'est pas `Jeux`.

### Les étiquettes dans la taxonomie

Avec `phil/taxonomy-bundle` enregistré, les étiquettes d'un événement sont celles de la taxonomie, partagées avec le
reste du site : le port `EventTagging` et son adaptateur `TaxonomyEventTagging`, où un événement est la ressource
`phil_calendar_event`. Ils se branchent d'eux-mêmes : rien à configurer.

- `Jeux de rôle` et `jeux de role` sont une seule étiquette : l'événement garde le nom que la taxonomie connaît, le
  premier donné.
- Un nom dont la taxonomie ne fait aucun slug (une ponctuation seule) est laissé de côté.
- L'événement garde une copie des noms dans sa ligne, pour que les vues filtrent sans demander à la taxonomie pour
  chaque événement. Après que des étiquettes ont été renommées ou fusionnées dans la taxonomie,
  `phil:calendar:refresh-tags` (`Calendar::refreshTags()`) donne aux événements les nouveaux noms ; d'ici là, un filtre
  par le nouveau nom ne trouve rien. Un événement étiqueté avant l'arrivée de la taxonomie lui donne ses propres mots.
  La commande lit chaque événement : lancez-la depuis la console.
- L'événement est d'abord enregistré, puis ses étiquettes sont données à la taxonomie, et il n'est enregistré de
  nouveau que si la taxonomie les nomme autrement.
- `Calendar::remove()` supprime l'événement, puis retire ses étiquettes de la taxonomie ; un événement supprimé par le
  seul dépôt les laisse.

```php
$taxonomy->taggedWith($taxonomy->tag('jeux de rôle'), TaxonomyEventTagging::RESOURCE); // the events, as resources
```

## `Calendar`

Le service avec lequel une application dialogue.

| Méthode | Ce qu'elle fait |
|---|---|
| `schedule(EventDraft)` | Un nouvel événement, enregistré |
| `apply(Event, EventDraft)` | Modifie un événement, enregistré |
| `remove(Event)` | Le supprime, avec ses organisateurs, et retire ses étiquettes de la taxonomie |
| `refreshTags()` | Redonne aux événements les noms de leurs étiquettes dans la taxonomie ; combien ont changé |
| `host(Event, kind, reference)`, `unhost(…)` | Ajoute un organisateur une seule fois ; le retire |
| `occurrences(from, through, ?kind, ?reference, ?tag)` | Les occurrences d'une période, par début puis par titre, d'un organisateur ou d'une étiquette |
| `events(from, through, ?kind, ?reference, ?tag)` | Les événements qui ont une occurrence dans la période, chacun lu jusqu'à sa première |
| `isCut()` | Si le budget de la requête a laissé des événements de côté depuis sa dernière remise à zéro |

Le début et la fin d'un brouillon se lisent à l'heure locale du fuseau horaire du brouillon : *19:00* en
`America/Toronto` est 19 h à Toronto, quel que soit le fuseau horaire du `DateTimeImmutable` que le formulaire a donné. Un événement garde cette heure locale
et son fuseau horaire, son début et sa fin conservés en UTC ; ceux-ci et les instants que le bundle consigne pour
lui-même — une présence, le choix d'un membre, l'émission du secret d'un flux — sont des colonnes de type
`datetime_utc_immutable`, écrites et relues en UTC quel que soit le fuseau horaire de PHP ou de la base.

### Le budget d'une requête

Toutes les listes d'occurrences d'une requête — les vues, les flux, le calendrier d'un membre, les mois imprimés —
puisent dans un même `ExpansionBudget` : un nombre d'occurrences, `budget.occurrences`, 10 000 par défaut, et un nombre
d'événements récurrents lus, `budget.expansions`, 500 par défaut. Un événement récurrent coûte une expansion chaque fois
qu'il est lu, quoi qu'il donne : une règle est parcourue jusqu'aux bornes de `phil/recurrence` même quand aucune
occurrence ne tombe dans la période, de sorte qu'un calendrier de nombreuses règles qui ne correspondent jamais —
`FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30` — coûte du temps sans rien montrer. Un événement qui ne se répète pas ne coûte
aucune expansion.

Au-delà de l'une ou l'autre limite, `Calendar` ne lit plus d'événements : la liste contient ce qui a été lu, dans
l'ordre, la page s'affiche, et l'arrêt est consigné une fois par requête comme un avertissement, sur le canal
`phil_calendar` avec Monolog, avec ce qui a été dépensé. Un événement qui remplit les occurrences restantes est coupé.
`isCut()` le dit, et le composant imprime une note sous sa vue. Mille règles de ce genre, lues pour la vue d'une année et
pour un flux, prennent environ 2 s sur un portable sans le budget et 0,6 s avec ses valeurs par défaut ; le temps croît
avec le budget, non avec les événements.

Le bundle enregistre un seul budget, remis à zéro entre les requêtes d'un *worker* par `kernel.reset`. Une commande de
la console qui appelle le calendrier dans une boucle le remet à zéro entre ses unités de travail : `$budget->reset()`.
Un `Calendar` construit à la main sans budget donne à chaque appel un budget aux limites par défaut.

```yaml
phil_calendar:
    budget:
        occurrences: 10000   # 1 to 1 000 000
        expansions: 500      # 1 to 1 000 000
```

## Le dépôt

`EventRepository` est le port ; `DoctrineEventRepository` son adaptateur Doctrine, avec un alias vers le port. `all()`
donne chaque événement par son début, pour `refreshTags()`. `overlapping()` garde les événements qui commencent avant la
fin de la période et dont la série n'a pas fini à son début, d'un organisateur lorsqu'on en donne un ; `Calendar` en
déroule alors les règles.
