<!-- phil-dev source-sha256=8d3b881196c046b3c9113ce84ef03c21a7fc17d3aacab2f30b674d74003f228b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Reconstruire les événements d'une bibliothèque | Enregistrez son `Phil\Outbox\Contract\EventCodec` : par configuration automatique, ou avec l'étiquette `phil_outbox.codec` |
| Écouter un type sans classe d'événement | Nommez-le dans `generic_types`, et écoutez `Phil\Outbox\RecordedEvent` |
| Conserver les enregistrements ailleurs | Faites de `Phil\Outbox\Contract\OutboxStore` un alias d'un entrepôt de l'application qui réussit les tests de contrat de `Phil\Outbox\Test\OutboxStoreTestCase` |
| Informer d'autres écouteurs | Nommez un autre répartiteur PSR-14 dans `dispatcher`, ou activez le pont vers Messenger |
| Dater les enregistrements par une autre horloge | Nommez une horloge de Symfony dans `clock` |
| Décorer l'outbox | Décorez `Phil\Outbox\Contract\Outbox`, en gardant `record()` dans la transaction de l'appelant |

## Services

| Service | Classe |
|---|---|
| `Phil\Outbox\Contract\Outbox` (alias public) | `Phil\Outbox\StoreOutbox` |
| `Phil\Outbox\Contract\OutboxStore` (alias) | `DbalOutboxStore` ou `Phil\Outbox\Memory\InMemoryOutboxStore` |
| `Phil\Outbox\Codecs` | les codecs portant l'étiquette `phil_outbox.codec` |
| `Phil\Outbox\Relay` (public) | le relais de la configuration |
| `Phil\Outbox\Housekeeping` (public) | les enregistrements morts, la rétention, les comptes |
| `phil_outbox.dispatcher` | le répartiteur configuré, ou `MessengerDispatcher` |
| `RelayRunner` | les exécutions de la commande et du déclencheur planifié |
