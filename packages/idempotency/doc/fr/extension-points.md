<!-- phil-dev source-sha256=9162b86933fa978aef43c7ab9c6eaffb2420552edfe2893915395a5187fe37c4 -->
# Points d'extension

## `Contract\IdempotencyStore`

Un stockage à soi — une autre base de données, un service clé-valeur — implémente cinq méthodes :

| Méthode | Contrat |
|---|---|
| `find($scope, $key): ?IdempotencyRecord` | Ne retourne jamais un enregistrement expiré. |
| `reserve($scope, $key, $fingerprint, $expiresAt): bool` | **Atomique** : `false` quand un enregistrement non expiré est en cours ou terminé ; un enregistrement expiré ou échoué est remplacé. |
| `complete($scope, $key, $response, $expiresAt): void` | Attache la réponse et garde la clé jusqu'à la rétention, quelle que soit l'expiration de la réservation. |
| `fail($scope, $key, $expiresAt): void` | Marque la clé échouée jusqu'à la rétention, et laisse tomber toute réponse. |
| `release($scope, $key): void` | Oublie la clé, quel que soit son état. |

Terminer, faire échouer ou libérer une clé inconnue ne fait rien. Gardez l'empreinte, jamais la charge.

Le test de contrat de la bibliothèque s'exécute contre n'importe quel stockage : étendez
`Phil\Idempotency\Tests\Unit\Store\IdempotencyStoreTestCase` depuis les tests du paquet et retournez votre stockage
depuis `createStore(MockClock $clock)`.

## L'empreinte

`PayloadFingerprint::ofParts()` prend les parties que vous choisissez. Pour une opération dont la charge porte un champ
qui change à chaque tentative, laissez ce champ de côté ; pour une opération dont le sens tient en quelques champs,
prenez l'empreinte de ceux-là.

## La garde

`IdempotencyGuard` est finale. Pour changer la façon de répondre à une opération, enveloppez-la : les méthodes de la
garde sont les étapes, et `begin()` retourne la décision sur laquelle agir.
