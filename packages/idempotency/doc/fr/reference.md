<!-- phil-dev source-sha256=76fb2d72d1248c3414abec18a2b26220d290f61a030346989d8fc3f72f384b8a -->
# Référence

Tous les types vivent sous `Phil\Idempotency`.

| Type | Sorte | Résumé |
|---|---|---|
| `IdempotencyKey` | final readonly class | La clé d'une intention : `HEADER`, `MAX_LENGTH`, `generate()`, `fromString()`, `fromHeader()`, `toHeaderValue()`, `equals()`. |
| `IdempotencyScope` | final readonly class | À qui appartiennent ces clés : `MAX_LENGTH`, `of()`, `equals()`. |
| `PayloadFingerprint` | final readonly class | Le SHA-256 d'une opération : `$hex`, `ofRequest()`, `ofParts()`, `fromHex()`, `equals()`. |
| `StoredResponse` | final readonly class | La réponse gardée pour un rejeu : `$status`, `$headers`, `$body`, `of()`, `nothing()`. |
| `IdempotencyState` | enum à valeur chaîne | `InProgress` (`in_progress`), `Completed` (`completed`), `Failed` (`failed`). |
| `IdempotencyRecord` | final readonly class | Ce que contient un stockage : `$fingerprint`, `$state`, `$expiresAt`, `$response` ; `inProgress()`, `completed()`, `failed()`, `of()`, `secondsLeft()`. |
| `IdempotencyOutcome` | enum | `Proceed`, `Replay`, `Reused`, `InProgress`. |
| `IdempotencyReservation` | final readonly class | La prise d'une exécution sur une clé : `TOKEN_LENGTH`, `$scope`, `$key`, `$token`, `create()`. |
| `IdempotencyDecision` | final readonly class | `for()`, `$outcome`, `$existing`, `$reservation`, `reserved()`, `replay()`. |
| `IdempotencyGuard` | final readonly class | `begin()`, `complete()`, `fail()`, `run()`, `inProgress()`. |
| `IdempotentRun` | final readonly class | Ce que retourne `run()` : `$response`, `$replayed`. |
| `Contract\IdempotencyStore` | interface | `find()`, `reserve()`, `complete()`, `fail()`, `release()`. |
| `Store\DbalIdempotencyStore` | final readonly class | Le stockage sur une connexion Doctrine DBAL ; `purge()`, `reservation()`. |
| `Store\IdempotencyTable` | final class | `NAME`, `configure()`. |
| `Store\CacheIdempotencyStore` | final readonly class | Le stockage sur un pool PSR-6 sous un verrou Symfony. |
| `Messenger\IdempotencyMiddleware` | final readonly class | Traite un message reçu une fois par clé, et refuse une clé réutilisée avec un autre contenu sauf si `$compareContent` est faux ; `MAX_DEPTH`. |
| `Messenger\IdempotencyStamp` | final readonly class | `DEFAULT_SCOPE`, `$key`, `$scope`, `$fingerprint`. |
| `Contract\FingerprintedMessage` | interface | `idempotencyFingerprint()` : l'empreinte des champs de l'intention d'un message. |
| `Exception\IdempotencyException` | interface | Ce que lève la bibliothèque. |
| `Exception\InvalidIdempotencyValue` | final class | Une valeur qui enfreint sa règle ; `because()`. |
| `Exception\MissingIdempotencyKey` | final class | Aucune clé sur une requête qui en demande une ; `onRequest()`. |
| `Exception\IdempotencyKeyReused` | final class | La clé a servi avec une autre charge ; `withAnotherRequest()`. |
| `Exception\RequestInProgress` | final class | La première exécution se poursuit ; `$retryAfter`, `retryAfter()`. |
| `Exception\ReservationLost` | final class | Une autre exécution tient la clé depuis l'expiration de la réservation ; `toAnotherRun()`. |
