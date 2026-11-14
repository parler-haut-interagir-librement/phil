<!-- phil-dev source-sha256=23a217ee02b64a86c8f2f3b60f39580ee2411575f833c10b7b5ccde5036aef18 -->
# Points d'extension

## `Contract\IdempotencyStore`

Un stockage à soi — une autre base de données, un service clé-valeur — implémente cinq méthodes :

| Méthode | Contrat |
|---|---|
| `find($scope, $key): ?IdempotencyRecord` | Ne retourne jamais un enregistrement expiré. |
| `reserve($scope, $key, $fingerprint, $expiresAt): ?IdempotencyReservation` | **Atomique** : `null` quand un enregistrement non expiré est en cours ou terminé ; un enregistrement expiré ou échoué est remplacé. La réservation retournée, venue de `IdempotencyReservation::create()`, a un jeton que le stockage garde avec l'enregistrement. |
| `complete($reservation, $response, $expiresAt): void` | Attache la réponse et garde la clé jusqu'à la rétention, quelle que soit l'expiration de la réservation. |
| `fail($reservation, $expiresAt): void` | Marque la clé échouée jusqu'à la rétention, et laisse tomber toute réponse. |
| `release($scope, $key): void` | Oublie la clé, quel que soit son état. |

`complete()` et `fail()` **comparent et écrivent en une étape** : elles n'écrivent que si l'enregistrement porte encore
le jeton de `$reservation`, et lèvent `ReservationLost` sinon — quand une autre exécution a réservé la clé depuis, ou
que la clé a été libérée ou oubliée. Une mise à jour conditionnelle, un comparer-et-écrire ou un verrou donne cette
étape. Libérer une clé inconnue ne fait rien. Gardez l'empreinte, jamais la charge.

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
