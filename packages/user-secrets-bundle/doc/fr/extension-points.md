<!-- phil-dev source-sha256=e973fbecc9cbf289ff039aea57813bc285c629f9146b6a8e8673370e720a5e5e -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise.

## Ports

### `TokenRefresher`

`Phil\UserSecretsBundle\Application\Contract\TokenRefresher` actualise un jeton OAuth expiré d'un service. Un service qui
l'implémente reçoit l'étiquette `phil_user_secrets.token_refresher` par configuration automatique. Voir
[Jetons OAuth](oauth-tokens.md#écrire-un-fournisseur-dactualisation).

| Méthode | Contrat |
|---|---|
| `supports(ServiceName $service): bool` | S'il actualise les jetons de ce service |
| `refresh(ServiceName $service, OAuthToken $token): OAuthToken` | Le nouveau jeton, tiré du point de terminaison des jetons du service ; ne journalise jamais ni l'un ni l'autre jeton |

### `TokenRevoker`

`Phil\UserSecretsBundle\Application\Contract\TokenRevoker` révoque un jeton OAuth auprès du service qui l'a émis, une
fois qu'il a disparu localement. Un service qui l'implémente reçoit l'étiquette `phil_user_secrets.token_revoker` par
configuration automatique. Voir [Jetons OAuth](oauth-tokens.md#écrire-un-révocateur).

| Méthode | Contrat |
|---|---|
| `supports(ServiceName $service): bool` | S'il révoque les jetons de ce service |
| `revoke(ServiceName $service, OAuthToken $token): void` | Révoque le jeton auprès du service — RFC 7009, le jeton d'actualisation d'abord quand il y en a un ; lève une exception quand le service ne l'a pas fait. Ne journalise jamais le jeton, ni ne le met dans le message d'une exception |

### `RefreshLock`

`Phil\UserSecretsBundle\Application\Contract\RefreshLock` laisse un seul processus à la fois actualiser un jeton donné.
`SymfonyRefreshLock`, sur le composant Lock de Symfony, est câblé quand une fabrique de verrous est définie ; une
application le remplace en réglant l'argument `$lock` de `UserSecrets`.

| Méthode | Contrat |
|---|---|
| `synchronized(Owner $owner, SecretName $name, Closure $critical): mixed` | Exécute `$critical` pendant qu'aucun autre processus ne l'exécute pour le même secret, et renvoie ce qu'elle renvoie ; libère le verrou quand elle lève une exception |

### `SecretAuditor`

`Phil\UserSecretsBundle\Application\Contract\SecretAuditor` reçoit chaque changement et chaque lecture. Un service qui
l'implémente reçoit l'étiquette `phil_user_secrets.auditor` par configuration automatique ; `LoggerAuditor` est fourni
derrière `log_events`. Voir [Audit et vie privée](audit-and-privacy.md).

| Méthode | Contrat |
|---|---|
| `record(SecretEvent $event): void` | Traite un `SecretEvent` — sa nature, son propriétaire, le nom du secret, le moment et, pour une lecture, le but ; jamais une valeur. Appelée après l'enregistrement du changement ; une exception parvient à l'appelant |

### `StoredSecretRepository`

`Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` contient les secrets scellés ; c'est un alias public
du stockage que choisit `store`. Un stockage propre à l'application borne `toReseal()` par sa limite et au-delà du secret
`$after`, par propriétaire et par nom, et compte ces mêmes secrets par clé dans `countToReseal()`, pour la rotation
des clés ; lit la ligne là où elle est conservée dans `fresh()`, au-delà de tout cache ; et n'enregistre dans
`replace()` que si la ligne détient encore la valeur scellée précédente et n'est pas révoquée — un seul `UPDATE`
conditionnel —, ce sur quoi s'appuie l'actualisation d'un jeton.

### `EnvelopeCipher`

`Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher` scelle et ouvre sous une clé maîtresse nommée :
`KeyManagementCipher`, par le composant KeyManagement de Symfony. Le format est celui du bundle : une application
choisit le KMS, pas le chiffrement. Voir [Chiffrement et rotation des clés](encryption.md).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `kms` | Le client de KeyManagement qui scelle — toute `DataKeyGeneratorInterface` : AWS KMS, Azure Key Vault, Google Cloud KMS, Vault Transit, un client `sodium://` local — ; `null` pour le client par défaut du composant |
| `current_key` | La clé maîtresse sous laquelle chaque nouvelle valeur est scellée |
| `refresh_leeway` | Combien de temps à l'avance un jeton OAuth est actualisé |
| `lock_factory`, `refresh_lock_ttl` | Le verrou sous lequel une actualisation s'exécute, et pour combien de temps au plus |
| `log_events` | Si chaque événement est aussi journalisé |
| `store`, `entity_manager` | L'endroit où les secrets sont conservés |

Voir [Configuration](configuration.md).

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\UserSecretsBundle\Application\UserSecrets` | Stocke, liste, lit pour une tâche du propriétaire, révoque, supprime et efface, ici et auprès du service ; public |
| `Phil\UserSecretsBundle\Application\KeyRotation` | Scelle de nouveau sous la clé maîtresse courante ; public |

Avec `phil/privacy-bundle`, le module `user_secrets` répond aux demandes d'accès et d'effacement.

Les écrans du propriétaire — ajouter, lister, révoquer — sont ceux de l'application : le bundle ne fournit ni formulaire,
ni contrôleur, ni gabarit. Voir [Stocker et lire les secrets](secrets.md#écrans-du-propriétaire).
