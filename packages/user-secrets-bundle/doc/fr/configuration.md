<!-- phil-dev source-sha256=e6f6f850fa96d1b2d4ff785d5c01b6f46762e1aeeb55fdf9d4d152548a1ea869 -->
# Configuration

```yaml
# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026         # required: a master key of the KMS
    kms: null                  # the component's default client, or a service such as key_management.vault
    store: doctrine            # or "memory", for tests
    entity_manager: default
    refresh_leeway: 60
    lock_factory: null         # FrameworkBundle's lock.factory when defined; false for none; or a service
    refresh_lock_ttl: 30
    log_events: false
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `current_key` | — | La clé maîtresse du KMS qui scelle chaque nouvelle valeur — une clé d'un DSN `sodium://`, un alias, un ARN —, écrite à côté de chaque valeur qu'elle scelle. |
| `kms` | `null` | Le service d'un client du composant KeyManagement de Symfony — toute `DataKeyGeneratorInterface`, comme `key_management.<client>` — qui scelle les valeurs ; `null` pour le client par défaut du composant. [Chiffrement et rotation des clés](encryption.md) explique les deux, et le client `sodium://` local. |
| `store` | `doctrine` | L'endroit où les secrets sont conservés : Doctrine, ou un tableau PHP qui dure le temps d'un processus, pour les tests. |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine de la table. |
| `refresh_leeway` | `60` | Combien de secondes avant son expiration un jeton OAuth est déjà actualisé, pour qu'il n'expire pas pendant l'appel pour lequel il est lu. |
| `lock_factory` | `null` | La fabrique de verrous sous laquelle un seul processus à la fois actualise un jeton OAuth : `null` pour le `lock.factory` de FrameworkBundle quand `symfony/lock` est installé et `framework.lock` configuré, et aucun verrou sinon ; `false` pour aucun verrou ; ou le service d'une autre `LockFactory`, qui doit être défini. [Jetons OAuth](oauth-tokens.md#actualisations-concurrentes) explique pourquoi. |
| `refresh_lock_ttl` | `30` | Combien de secondes au plus une actualisation garde son verrou, pour qu'un processus qui meurt ne le garde pas ; plus que le temps que prend le point de terminaison de jeton le plus lent. |
| `log_events` | `false` | Si chaque événement est aussi écrit dans le service `logger`, au niveau `info`. |

Le KMS est vérifié à la construction du conteneur : sans client du composant, ou avec un `kms` qui ne nomme aucun
service, le conteneur s'arrête avec un message qui dit quoi changer ; un `lock_factory` qui ne nomme aucun service fait de
même. Les clés maîtresses appartiennent au KMS : le
bundle ne les lit jamais.

## Services

| Service | Public | Rôle |
|---|---|---|
| `Phil\UserSecretsBundle\Application\UserSecrets` | oui | Stocker, lister, lire, révoquer, supprimer, effacer |
| `Phil\UserSecretsBundle\Application\KeyRotation` | oui | Sceller de nouveau sous la clé maîtresse courante |
| `Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` | oui (alias) | Le stockage choisi par `store` |

Le paramètre `phil_user_secrets.kms` contient le service du KMS, et `phil_user_secrets.lock_factory` celui de la fabrique
de verrous. Quand cette fabrique est définie, le service privé
`Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock` est le verrou d'actualisation de `UserSecrets`. Le
service `logger`, quand il est défini, reçoit les échecs de la révocation auprès du service.

Par configuration automatique, un service qui implémente `TokenRefresher` reçoit l'étiquette
`phil_user_secrets.token_refresher`, un service qui implémente `TokenRevoker` reçoit l'étiquette
`phil_user_secrets.token_revoker`, et un service qui implémente `SecretAuditor` reçoit l'étiquette
`phil_user_secrets.auditor`. Quand `phil/privacy-bundle` est installé, le module `user_secrets` est enregistré avec ses
étiquettes d'exportateur et d'effaceur ; avec `symfony/console`, les deux commandes le sont aussi.
