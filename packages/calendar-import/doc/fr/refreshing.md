<!-- phil-dev source-sha256=b135db5c5234c5dc273cc558f0402de44d5cc5f8690ce00f91e4aad43e593cf4 -->
# Rafraîchir une source

`CalendarImporter` relit une source et dit ce qui a changé depuis sa dernière lecture. Il ne garde rien lui-même :
l'état de chaque source et les surcharges des éditeurs se trouvent derrière deux ports, que l'application implémente.

```php
use Phil\CalendarImport\CalendarImporter;
use Phil\CalendarImport\ChangeKind;
use Phil\CalendarImport\Source\IcsUrlSource;

$importer = new CalendarImporter(IcsUrlSource::create(), $stateStore, $overrideStore, $clock);
$outcome = $importer->refresh($source);

foreach ($outcome->changesOf(ChangeKind::Added) as $change) {
    // $change->draft: an ImportedDraft to create in the application's calendar
}
```

## Les instantanés

Chaque lecture réussie donne un `Snapshot` : le moment où il a été pris, et l'`EventVersion` de chaque événement, par
sa clé. Une version contient le `SEQUENCE` de l'événement, son `LAST-MODIFIED`, le SHA-256 de son contenu — chaque
champ que la bibliothèque donne, sauf `DTSTAMP`, `SEQUENCE` et `LAST-MODIFIED` — et le SHA-256 de chaque champ qu'un
éditeur peut remplacer. Un instantané contient des versions, jamais des événements : il est petit, et ne contient
aucun texte du flux.

`Snapshot::toArray()` et `Snapshot::fromArray()` l'écrivent et le relisent en tableaux simples, pour une colonne JSON
ou un cache ; `fromArray()` lance `InvalidSource` sur des données qui ne tiennent pas ensemble.

## Les différences

`$snapshot->compare($previous)` donne un `SnapshotDiff` :

- **added** — les clés absentes de l'instantané précédent ; à partir d'aucun instantané, chaque événement est ajouté ;
- **changed** — les clés dont le contenu diffère, chacune avec sa `ChangeReason` et les `Field` qu'elle change ;
- **removed** — les clés absentes de cet instantané.

La raison d'un changement est, dans cet ordre : un nouveau `SEQUENCE`, sinon un nouveau `LAST-MODIFIED`, sinon le
contenu seul. Un `SEQUENCE` ou un `LAST-MODIFIED` qui bouge alors que le contenu reste le même n'est pas un
changement : les flux qui estampillent chaque événement à chaque exportation n'inondent pas l'application de
changements.

Une occurrence modifiée par un `RECURRENCE-ID` a sa propre clé ; elle est donc ajoutée, modifiée et supprimée
séparément.

## Ce que fait un rafraîchissement

1. Il charge l'état de la source (`SourceState`), ou en crée un.
2. Il récupère la source avec les validateurs de sa dernière réponse.
3. **Quand la source échoue** (`SourceUnavailable`), l'état garde son dernier instantané, enregistre l'échec — son
   `FailureKind`, son message, son heure, son statut — et compte les échecs consécutifs. Le résultat ne rapporte aucun
   changement : le dernier bon état demeure jusqu'à ce qu'une lecture le remplace.
4. **Quand rien n'a changé** (`304 Not Modified`), l'état garde son instantané, prend les nouveaux validateurs et
   l'heure de la vérification, et efface l'échec.
5. **Quand un calendrier est lu**, un nouvel instantané est pris et comparé au dernier. Un événement refusé cette fois
   qui avait été lu auparavant garde sa dernière version, de sorte qu'une ligne brisée dans un flux n'est pas lue
   comme une suppression.
6. Les surcharges de la source sont appliquées aux brouillons, et un champ qu'un éditeur a remplacé et que la source
   a aussi modifié est signalé comme un conflit de son changement.
7. L'état est enregistré, et un `ImportOutcome` est retourné.

| `ImportOutcome` | Contient |
|---|---|
| `read` | si un calendrier a été lu cette fois |
| `state` | l'état enregistré |
| `changes` | les `Change` : genre (`Added`, `Changed`, `Removed`), clé, brouillon (aucun pour une suppression), raison, conflits |
| `drafts` | le brouillon de chaque événement lu, par clé |
| `rejected` | les événements refusés, chacun avec sa `Rejection` |
| `isFailure()` | si la source a échoué cette fois |

## Rafraîchir selon un horaire

`refreshDue($sources)` relit les sources qui sont dues selon la `RefreshPolicy`, et laisse les autres. Un bundle ou
une application l'appelle depuis une tâche planifiée ; la bibliothèque elle-même ne planifie rien.

```php
use Phil\CalendarImport\State\RefreshPolicy;

new RefreshPolicy(interval: 3_600, retry: 300, backoff: 86_400);
```

- Une source jamais vérifiée est due.
- Une source qui a réussi est due `interval` secondes après sa dernière vérification (une heure par défaut).
- Une source qui échoue est due `retry` secondes après sa dernière vérification, doublées pour chaque échec
  consécutif après le premier, `backoff` secondes au plus (5 minutes, puis 10, 20… jusqu'à un jour par défaut) : une
  source en panne est interrogée de moins en moins souvent, et jamais moins d'une fois par `backoff`.

Chaque délai va de 60 secondes à 7 jours, et `backoff` n'est jamais plus court que `retry` ; sinon `InvalidLimits`
est lancée.

## Les surcharges des éditeurs

Un éditeur peut modifier un événement importé sans perdre sa modification à la lecture suivante. Un `EditorOverride`
nomme la clé de l'événement, et remplace son titre, son lieu, sa description et ses étiquettes, ou le masque :

```php
use Phil\CalendarImport\Override\EditorOverride;

new EditorOverride('board-games-night@calendar.example.org', title: 'Board games night', tags: ['games']);
new EditorOverride('closed-day@calendar.example.org', hidden: true);
```

Un champ nul est celui de la source. La surcharge est gardée à part des événements lus, derrière le port
`OverrideStore`, et appliquée de nouveau à chaque rafraîchissement : elle survit à chaque lecture, jusqu'à ce que
l'éditeur la retire.

Quand la source modifie un champ qu'une surcharge remplace, le changement de cet événement liste le champ dans ses
`conflicts` : l'application peut dire à l'éditeur que la source a un nouveau titre sous le sien. Le brouillon porte
toujours la valeur de l'éditeur.

## Les brouillons

Un `ImportedDraft` est un événement importé prêt à devenir un événement du calendrier de l'application. Il est
neutre : il n'appartient à aucune bibliothèque de calendrier, et un adaptateur de l'application le transforme en ses
propres événements (voir [Points d'extension](extension-points.md)).

| Champ | Contient |
|---|---|
| `sourceId`, `key`, `uid` | d'où vient l'événement : la source, sa clé dans celle-ci, son UID dans celle-ci |
| `recurrenceId` | l'occurrence qu'il remplace, pour une occurrence modifiée |
| `start`, `end`, `allDay`, `floating`, `timezone` | ses heures |
| `title`, `description`, `location`, `tags` | ses textes, ceux de l'éditeur à la place de ceux qu'il a remplacés |
| `url`, `status`, `rule`, `additions`, `exclusions` | son adresse, son statut et sa récurrence |
| `hidden` | si un éditeur l'a masqué |
| `overridden` | les `Field` qu'un éditeur a remplacés |

`isCancelled()` indique un événement ou une occurrence annulés.

## Les magasins

| Port | Garde | Implémenté par |
|---|---|---|
| `State\SourceStateStore` | le `SourceState` de chaque source : instantané, validateurs, heures, échec | l'application ou un bundle (une table, un cache) |
| `Override\OverrideStore` | les `EditorOverride` de chaque source | l'application ou un bundle |

Les tests utilisent des magasins en mémoire, sous `tests/Double` ; ils ne font pas partie de la bibliothèque, qui ne
garde rien d'une requête à la suivante.
