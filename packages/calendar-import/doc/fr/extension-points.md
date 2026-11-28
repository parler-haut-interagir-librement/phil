<!-- phil-dev source-sha256=fc81575fefeae466b05f331b668fa74e602170132578c7b1e07f9d676b09d00e -->
# Points d'extension

La bibliothèque en est à ses versions `0.x` : ses ports, ses objets-valeurs et ses exceptions peuvent changer dans une
version mineure, ce que dit son CHANGELOG. Ils sont destinés à être stables à partir de la `1.0`.

## Les ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Source\EventSource` | `IcsUrlSource`, ou un autre protocole | donne les événements d'un calendrier, ou dit que rien n'a changé |
| `State\SourceStateStore` | l'application ou un bundle | garde l'état de chaque source entre deux lectures |
| `Override\OverrideStore` | l'application ou un bundle | donne les surcharges des éditeurs d'une source |

Une source d'un autre protocole — une API de calendrier, un flux fédéré — implémente `EventSource` : elle donne un
`ReadResult` de `SourceEvent`, chacun avec un UID, et lance `SourceUnavailable` quand elle ne donne rien. Les
instantanés, les différences, les surcharges et les brouillons fonctionnent alors de la même façon, quel que soit le
protocole.

Un magasin garde le `Snapshot` tel que `toArray()` l'écrit — des tableaux simples, petits, sans les textes du flux —
à côté des validateurs, des heures et de l'échec de la source. Il ne garde aucun état en mémoire entre deux requêtes.

## Écrire dans un calendrier

La bibliothèque donne des `ImportedDraft` et ne dépend d'aucune bibliothèque de calendrier. Un adaptateur de
l'application, ou d'un bundle construit sur cette bibliothèque, transforme les changements d'un `ImportOutcome` en
événements de son propre calendrier :

| Changement | L'adaptateur |
|---|---|
| `Added` | crée un événement à partir du brouillon, marqué avec `sourceId` et `key`, pour pouvoir le retrouver |
| `Changed` | trouve l'événement par `sourceId` et `key`, et applique le brouillon ; `conflicts` dit quels champs d'éditeur la source a changés sous eux |
| `Removed` | trouve l'événement par `sourceId` et `key`, et le supprime ou le dépublie |

Un brouillon `hidden` reste hors du calendrier publié ; un brouillon dont `isCancelled()` est vrai est affiché annulé,
ou supprimé, comme le calendrier le fait de ses propres événements. Un brouillon garde son fuseau, qu'il soit sur une
journée entière ou flottant, et sa `RecurrenceRule`, ses ajouts et ses exclusions de `phil/recurrence`, de sorte que
l'adaptateur n'a besoin d'aucune autre lecture du flux.

Un bundle construit sur cette bibliothèque ajouterait :

- une tâche planifiée qui appelle `CalendarImporter::refreshDue()` pour les sources que l'application liste ;
- des magasins Doctrine pour les états et les surcharges ;
- l'adaptateur ci-dessus, de `ImportedDraft` au type de brouillon de son calendrier, qui écrit chaque changement par le
  propre service du calendrier — planifier, appliquer, supprimer.

## Limites et politique

`ReaderLimits`, donné à `ICalendarReader` et à `IcsUrlSource::create()`, fixe les bornes d'un flux ; `RefreshPolicy`,
donnée à `CalendarImporter`, fixe le moment où une source est relue. Un `SafeHttpClient` configuré par l'application —
ses propres délais, un mandataire, un agent utilisateur — est donné au constructeur d'`IcsUrlSource`.
