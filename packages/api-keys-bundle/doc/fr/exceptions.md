<!-- phil-dev source-sha256=9b99e316d7dd4625d515b090faa2143ef3148c95ff3e73c604061811d9f1e18d -->
# Exceptions

Chaque exception du bundle implémente `Phil\ApiKeysBundle\Domain\Exception\ApiKeysException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `Domain\Exception\MalformedToken` | `InvalidArgumentException` | `TokenFormat::parse()` lit un texte d'une autre longueur, d'un autre préfixe ou d'autres séparateurs, avec un caractère qu'aucun jeton ne contient, ou dont la somme de contrôle ne correspond pas; le message ne répète jamais le texte |
| `Domain\Exception\InvalidTokenValue` | `InvalidArgumentException` | un préfixe mal formé, un propriétaire ou un nom hors limites, une portée donnée deux fois, une expiration qui ne suit pas la création ou qui dépasse la durée de vie maximale, le renouvellement d'un jeton révoqué ou expiré, une clé d'empreinte de moins de 32 octets, donnée deux fois ou une clé de trop, un nom de portée mal formé |
| `Infrastructure\SecretScanning\Exception\UnreadableReport` | `InvalidArgumentException` | un rapport de l'analyse de secrets de GitHub ou un document de ses clés qui n'est pas du JSON, n'a pas la forme attendue ou dépasse une borne; le point de terminaison répond `400`, ou ne garde aucune clé |
| `Application\Exception\UnknownScope` | `InvalidArgumentException` | une portée que la configuration ne déclare pas; le message énumère celles qu'elle déclare |
| `Application\Exception\TokenNotFound` | `RuntimeException` | aucun jeton de cet identifiant, ou le jeton d'un autre propriétaire |
| `Application\Exception\InvalidCredentials` | `RuntimeException` | `ApiTokens::verify()` refuse un jeton; `failure` est un `CredentialFailure` : `Malformed`, `Unknown`, `Revoked` ou `Expired` |

Le gestionnaire de jetons transforme `InvalidCredentials` en `BadCredentialsException` de Symfony, en gardant
l'exception précédente, et l'authentificateur répond `401`. La page des membres transforme `TokenNotFound` en `404`. Une configuration mal formée fait
échouer la construction du conteneur avec l'`InvalidConfigurationException` de Symfony.
