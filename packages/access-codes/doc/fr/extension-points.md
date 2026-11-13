<!-- phil-dev source-sha256=3394eee9d9ff545593955ba3789e83e042d818ddd9539be602e61323ab4d606c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque est du PHP simple et ne déclare aucun service. `AccessCodes` reçoit un magasin, un calculateur
d'empreintes, une fabrique de limiteurs de débit, une horloge et, facultativement, un format et un générateur aléatoire.

## `CodeStore`

`Phil\AccessCodes\Contract\CodeStore` — là où vivent les lots et les empreintes. `InMemoryCodeStore` et
`Dbal\DbalCodeStore` sont fournis ; voir [Clés et stockage](storage.md).

| Méthode | Contrat |
|---|---|
| `add(Batch $batch, array $hashes): void` | Ajoute le lot et une empreinte par code, tout ou rien ; refuse par `InvalidConfiguration` un identifiant de lot ou une empreinte déjà stockés |
| `redeem(array $candidates, string $redeemer, DateTimeImmutable $now): ?string` | Marque le code comme échangé seulement s'il n'est ni échangé, ni révoqué, ni expiré à `$now`, de façon atomique ; renvoie son lot, ou `null` |
| `find(array $candidates): ?CodeState` | Ce que le magasin sait du code, ou `null` |
| `batch(string $batchId): ?Batch` | Le lot, ou `null` |
| `revoke(string $batchId, DateTimeImmutable $now): int` | Marque le lot et ses codes non échangés comme révoqués ; renvoie combien de codes, 0 pour un lot déjà révoqué ou inconnu |
| `counts(string $batchId): BatchCounts` | Les codes au total, échangés et révoqués |

Un magasin propre à l'application reçoit des empreintes, jamais des codes, et tient la promesse de `redeem()` : de deux
appels au même moment pour un même code, un seul l'emporte.

## Les autres arguments

| Argument | Quoi passer |
|---|---|
| `CodeHasher` | Les clés ordonnées, lues dans le coffre de secrets |
| `RateLimiterFactoryInterface` | N'importe quelle fabrique de limiteurs de débit de Symfony, sur un magasin partagé par les serveurs |
| `Psr\Clock\ClockInterface` | L'horloge de l'application ; une `MockClock` dans les tests |
| `CodeFormat` | Un autre alphabet, une autre longueur, un autre symbole de contrôle ou un autre groupement |
| `Random\Randomizer` | Le moteur `Secure` par défaut ; un moteur à germe dans les tests seulement |
