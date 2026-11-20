<!-- phil-dev source-sha256=4baa5d7ee46f85ecadcb9b7aed629068d12015ab920f3fc23dd3788ebd8e90b8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

La bibliothèque est du PHP simple et ne déclare aucun service. L'outbox, le relais et l'entretien prennent ces ports.

## `EventCodec`

`Phil\Outbox\Contract\EventCodec` — reconstruit les événements de certains types. Voyez [Codecs](codecs.md).

| Méthode | Contrat |
|---|---|
| `types(): array` | Les types d'événement qu'il reconstruit : la liste d'autorisation est l'union de ceux de tous les codecs |
| `decode(OutboxRecord $record, Payload $payload): object` | L'événement que le relais répartit ; lance `UndeliverableRecord` quand l'enregistrement ne peut jamais en devenir un ; n'exécute jamais `unserialize()` |

## `OutboxStore`

`Phil\Outbox\Contract\OutboxStore` — où les enregistrements sont conservés. Voyez [Entrepôts](stores.md). Un entrepôt
propre à l'application réussit les tests de contrat de `Phil\Outbox\Test\OutboxStoreTestCase`, ajoute un enregistrement
dans la transaction de l'appelant, ne donne jamais un enregistrement à deux réservations tant que son bail court, et
traite un enregistrement écarté comme un enregistrement livré : réglé, il ne retient jamais sa clé d'ordre et il est
purgé après sa rétention.

## `Outbox`

`Phil\Outbox\Contract\Outbox` — le port par lequel une bibliothèque enregistre. `StoreOutbox` en est l'implémentation ;
une application peut la décorer, pour compter les enregistrements par exemple, tant que `record()` s'exécute dans la
transaction de l'appelant.

## Le répartiteur

N'importe quel `EventDispatcherInterface` de PSR-14 : celui de l'application, ou le pont de phil/outbox-bundle qui
relaie vers un bus de Messenger.

## L'horloge et le recul

N'importe quel `ClockInterface` de PSR-20, `SystemClock` en UTC par défaut. `Backoff` prend son premier délai, son délai
le plus long et son nombre de tentatives.
