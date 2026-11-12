<!-- phil-dev source-sha256=1e7757489dc7b358bfcdf7457c843b759518bef7335f1f53e3376591582862bb -->
# La règle

## Les clés

`IdempotencyKey` est la clé d'une intention. **Côté client, une clé par intention, gardée d'une tentative à
l'autre** : une clé générée à chaque tentative fait de chaque nouvelle tentative une nouvelle requête, soit le doublon
que la clé existe pour empêcher. Générez-la là où naît l'intention — `IdempotencyKey::generate()`, un UUIDv4
aléatoire — et portez-la dans le message ou la tâche qui réessaie.

| Membre | Description |
|---|---|
| `HEADER` | `'Idempotency-Key'` |
| `MAX_LENGTH` | `255` |
| `generate(): self` | Un UUIDv4 aléatoire, 122 bits d'un générateur cryptographique. |
| `fromString(string $value): self` | 1 à 255 octets d'ASCII imprimable, sans `"`, `\` ni espaces en bordure ; les espaces intérieures sont permises. |
| `fromHeader(?string $header): self` | Rogne l'en-tête ; lève `MissingIdempotencyKey` s'il est absent ou blanc ; retire une paire de guillemets englobants ; applique ensuite `fromString()`. |
| `toHeaderValue(): string` | La forme entre guillemets, `"abc-123"`. |
| `equals(self $other): bool` | Comparaison exacte. |

L'en-tête est spécifié par le brouillon IETF `draft-ietf-httpapi-idempotency-key-header`, qui écrit la valeur comme
une chaîne de champ structuré, entre guillemets ; beaucoup de clients l'envoient sans. Le lecteur accepte les deux,
l'écrivain envoie la forme entre guillemets.

## Les portées

`IdempotencyScope::of(string $value)` dit à qui appartiennent ces clés : un client d'API, une session, l'expéditeur
d'un webhook, un bus. **Les clés ne sont jamais globales** : dans un espace partagé, la clé d'un appelant supprime la
requête d'un autre, et aucun des deux ne voit d'erreur. Une portée fait 1 à 255 octets sans caractère de contrôle.

## Les empreintes

`PayloadFingerprint` est le SHA-256 de ce qu'on a demandé à l'opération, gardé à côté de la clé **à la place de la
charge**.

- `ofRequest(string $method, string $target, string $body)` couvre la méthode en majuscules, le chemin et la requête,
  et le corps brut, octet pour octet. Réutiliser une clé sur un autre point de terminaison est une réutilisation ; un
  client qui réencode le corps entre deux tentatives est refusé, la réponse sûre quand le serveur ne peut pas savoir
  si le changement compte.
- `ofParts(string ...$parts)` couvre toute autre opération : une classe de message, le type et l'identifiant d'un
  événement de webhook, les champs soumis d'un formulaire. Choisissez les parties qui disent ce que fait l'opération,
  et laissez de côté ce qui change d'une tentative à l'autre, comme un horodatage du client.
- Chaque partie est préfixée par sa longueur en octets et un deux-points avant le hachage : aucun choix de parties ne
  se confond avec un autre par concaténation. `ofRequest('post', '/api/things', '{"a":1}')` hache
  `4:POST11:/api/things7:{"a":1}`. **Ce format est fixé par un test** : les empreintes sont stockées, et le changer
  ferait de chaque clé stockée une réutilisation.
- `fromHex()` en relit une depuis le stockage ; `equals()` compare en temps constant.

## Les trois états

| `IdempotencyState` | Sens | Expire |
|---|---|---|
| `InProgress` | Réservée, l'exécution se poursuit. | Après la réservation : plus longue que l'exécution la plus lente, bien plus courte que la rétention. Un processus tué en cours d'exécution ne garde sa clé que ce temps-là. |
| `Completed` | Répondue : la `StoredResponse` est rejouée. | Après la rétention. |
| `Failed` | L'exécution a échoué d'une façon que l'appelant peut réessayer. | Après la rétention. |

Un `IdempotencyRecord` porte l'empreinte, l'état, l'expiration, et la réponse d'une exécution terminée.
`secondsLeft($now)` donne les secondes entières qui restent avant son expiration, au moins 1 : le `Retry-After` d'une
exécution en cours.

`StoredResponse::of(int $status, array $headers = [], string $body = '')` garde ce que le rejeu demande : un statut
entre 100 et 599, les en-têtes choisis pour le rejeu, le corps. `StoredResponse::nothing()` est le `204` d'une
opération qui ne retourne rien. Ce que contient le corps et combien de temps il est gardé appartiennent au calendrier
de conservation de l'application.

## Les quatre issues

`IdempotencyDecision::for(?IdempotencyRecord $existing, PayloadFingerprint $fingerprint)` est une fonction pure :

| Le stockage contient | Empreinte | Issue | Réponse HTTP |
|---|---|---|---|
| rien | toute | `Proceed` | Exécuter, puis garder la réponse. |
| un enregistrement | différente | `Reused` | Problème `422`. Retourner le premier résultat laisserait tomber sans bruit une requête corrigée. |
| en cours | même | `InProgress` | Problème `409` avec `Retry-After`. |
| échoué | même | `Proceed` | Exécuter de nouveau. |
| terminé | même | `Replay` | La réponse stockée. |

**L'empreinte est comparée avant l'état** : un appelant apprend son erreur dès la première nouvelle tentative, même
pendant que la première exécution se poursuit ou après son échec. `$decision->existing` est l'enregistrement que la
décision a lu, et `$decision->replay()` la réponse à retourner sur `Replay`.

Les réponses HTTP sont celles du brouillon de l'IETF pour l'en-tête `Idempotency-Key` : `422` à une clé réutilisée
avec une autre charge, qu'aucune nouvelle tentative ne corrigera, et `409` à une clé dont la première exécution se
poursuit, qu'une nouvelle tentative après `Retry-After` peut faire aboutir.

Ensuite : [la garde](guard.md).
