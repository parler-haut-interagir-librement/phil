<!-- phil-dev source-sha256=540ae0caa868fd7e3f52a9e0db12cb9ed3454813f06c31bb0714b329a20e2fdb -->
# Exceptions

Chaque exception implémente `Exception\IdempotencyException` et garde le parent SPL qui dit de quelle sorte d'échec il
s'agit.

| Exception | Parent | Quand | Réponse HTTP |
|---|---|---|---|
| `InvalidIdempotencyValue` | `InvalidArgumentException` | Une clé, une portée, une empreinte, un statut, un enregistrement ou une durée qui enfreint sa règle. | `400` pour une clé lue dans une requête |
| `MissingIdempotencyKey` | `RuntimeException` | `IdempotencyKey::fromHeader()` sur un en-tête absent ou blanc. | `400` |
| `IdempotencyKeyReused` | `RuntimeException` | La clé a d'abord servi avec une autre charge. | `422` |
| `RequestInProgress` | `RuntimeException` | La première exécution est encore en cours ; `$retryAfter` donne les secondes qui restent à sa réservation, au moins 1. | `409` avec `Retry-After` |

Répondez-y par des détails de problème RFC 9457. Ne renvoyez pas le message de `InvalidIdempotencyValue` pour une clé
lue dans une requête : dites plutôt ce qu'est une clé.

Ensuite : [points d'extension](extension-points.md).
