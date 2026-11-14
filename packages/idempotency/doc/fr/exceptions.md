<!-- phil-dev source-sha256=7090ae169418a691e569a744ff42f615a3ae26c4114dd8db343c74f88aa82347 -->
# Exceptions

Chaque exception implémente `Exception\IdempotencyException` et garde le parent SPL qui dit de quelle sorte d'échec il
s'agit.

| Exception | Parent | Quand | Réponse HTTP |
|---|---|---|---|
| `InvalidIdempotencyValue` | `InvalidArgumentException` | Une clé, une portée, une empreinte, un statut, un enregistrement ou une durée qui enfreint sa règle. | `400` pour une clé lue dans une requête |
| `MissingIdempotencyKey` | `RuntimeException` | `IdempotencyKey::fromHeader()` sur un en-tête absent ou blanc. | `400` |
| `IdempotencyKeyReused` | `RuntimeException` | La clé a d'abord servi avec une autre charge. | `422` |
| `RequestInProgress` | `RuntimeException` | La première exécution est encore en cours ; `$retryAfter` donne les secondes qui restent à sa réservation, au moins 1. | `409` avec `Retry-After` |
| `ReservationLost` | `RuntimeException` | L'exécution s'est terminée après l'expiration de sa réservation et la réservation de la clé par une autre exécution, ou après la libération de la clé : son issue n'a pas été gardée. | `500` ; allongez la réservation au-delà de l'exécution la plus lente |

Répondez-y par des détails de problème RFC 9457. Ne renvoyez pas le message de `InvalidIdempotencyValue` pour une clé
lue dans une requête : dites plutôt ce qu'est une clé.

Ensuite : [points d'extension](extension-points.md).
