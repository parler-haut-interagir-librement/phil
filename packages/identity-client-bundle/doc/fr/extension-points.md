<!-- phil-dev source-sha256=30a8489227de7f83ce237c54ac978eafe08ac59147e79e0dd794c9370020d74b -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise.

## Ports

Chacun est un alias que l'application fait pointer vers un service à elle.

| Port | Fourni | Ce que fait celui de l'application |
|---|---|---|
| `UserProvisioner` | `StatelessUserProvisioner` | Crée et retrouve les comptes ; voir [Approvisionnement](provisioning.md) |
| `ReturnToPolicy` | `DeclaredOriginsReturnToPolicy` | Une autre règle pour l'aboutissement d'une connexion ; retourne null pour écarter |
| `ReplayStore` | `CacheReplayStore` | Garde ailleurs les `jti` acceptés ; `claim()` reste atomique entre les serveurs |
| `RefreshOutcomes` | `EncryptedRefreshOutcomes` | Partage ailleurs les résultats de renouvellement ; le résultat contient des jetons, il ne doit donc pas être stocké en clair |

## Événements

Ils sont distribués par le répartiteur d'événements de l'application. Ils ne portent que des identifiants : sujet,
identifiant de session du fournisseur, noms de niveaux.

| Événement | Quand |
|---|---|
| `UserSignedIn` | Une connexion a abouti |
| `ProviderSessionEnded` | Un jeton de déconnexion a été accepté |
| `LocalSessionEnded` | Une session locale a été invalidée, avec sa `LocalSessionEndReason` |
| `ElevationRequested` | Le navigateur a été renvoyé chercher un niveau |
| `ElevationRefused` | Le fournisseur ne l'a pas atteint |
| `TokensRefreshed` | Le fournisseur a remplacé les jetons |
| `RefreshTokenRejected` | Le fournisseur a refusé le jeton de renouvellement |
| `ReturnToDiscarded` | Un `return_to` a été refusé |

L'événement `OidcAuthorizationRequestEvent` de Symfony reste disponible pour calculer d'autres paramètres à chaque
requête.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `claims` | Ce qui parvient à l'approvisionneur |
| `authorization_params` | Les paramètres supplémentaires de chaque requête d'autorisation |
| `elevation.levels` | L'échelle d'assurance |
| `return_to.origins` | Où une connexion peut aboutir |
| `paths` | Chaque route et page d'arrivée |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `security.authenticator.oidc_login.token_refresher.<firewall>` | Le service de renouvellement de Symfony, pour forcer un renouvellement |
| `OIDC_ASSURANCE:<level>` | L'attribut de `AssuranceVoter`, dans `#[IsGranted]` ou `is_granted()` |
