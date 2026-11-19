<!-- phil-dev source-sha256=bc962e30c07598fd938541529da0ed8a78ee46cbbd4812bf5c2d3520d6286e3b -->
# Exceptions

Les exceptions du bundle implémentent `Phil\UserSecretsBundle\Domain\Exception\UserSecretsException`. Aucune ne
transporte la valeur d'un secret ni une clé.

| Exception | Étend | Levée quand |
|---|---|---|
| `Domain\Exception\InvalidSecret` | `InvalidArgumentException` | Un propriétaire, un nom, un service, une valeur ou un but mal formé ; une charge utile d'identifiant de connexion de la mauvaise forme ; un lot de rotation inférieur à 1 |
| `Domain\Exception\RevokedSecret` | `RuntimeException` | Un secret révoqué est lu ou scellé de nouveau |
| `Application\Exception\SecretNotFound` | `RuntimeException` | Le propriétaire n'a aucun secret de ce nom, y compris quand la tâche d'un autre propriétaire le demande |
| `Application\Exception\WrongCredentialKind` | `LogicException` | Une clé d'API lue comme un jeton, ou un jeton comme une clé |
| `Application\Exception\ExpiredToken` | `RuntimeException` | Un jeton OAuth expiré qui ne peut pas être actualisé |
| `Application\Exception\RefreshConflict` | `RuntimeException` | Un jeton OAuth actualisé qui n'a pas pu être enregistré, sa ligne ayant été changée par d'autres processus à chacune des `UserSecrets::REFRESH_ATTEMPTS` tentatives |
| `Application\Exception\UnreadableSecret` | `RuntimeException` | Un texte chiffré qui ne s'ouvre pas : d'aucun format ni algorithme connu, son en-tête changé, mal formé, altéré, scellé sous une clé maîtresse que le KMS ne détient pas, copié d'une autre ligne, ne contenant aucun identifiant de connexion, ou que le KMS n'a pas ouvert — injoignable, refusant —, son exception conservée comme exception précédente |
| `Application\Exception\SealingFailed` | `RuntimeException` | Une valeur que le KMS n'a pas scellée — injoignable, refusant —, son exception conservée comme exception précédente |
| `Application\Exception\UnknownKey` | `RuntimeException` | Une clé courante pour laquelle le KMS n'a pas de clé maîtresse |

Exemples de messages :

```text
The owner has no secret "openai".
The secret "openai" was revoked: store a new value to use it again.
The OAuth token "github" has expired and cannot be refreshed: the owner must connect the service again.
The KMS has no master key "k2027" to seal with: check phil_user_secrets.current_key.
The sealed value of secret "openai" names no known algorithm.
```

L'échec d'un `TokenRevoker` n'est jamais levé : il est journalisé et audité, comme l'explique
[stocker et lire les secrets](secrets.md#révocation-auprès-du-service).

Une configuration sans `current_key` échoue à la construction du conteneur avec l'`InvalidConfigurationException` de
Symfony ; sans client du composant KeyManagement, ou avec un `kms` qui ne nomme aucun service, ou avec un
`lock_factory` qui ne nomme aucun service, avec une `LogicException` qui dit quoi changer. Un échec du KMS lui-même parvient à l'appelant sous forme de `UnreadableSecret`
ou de `SealingFailed`, avec l'exception propre au KMS comme exception précédente.
