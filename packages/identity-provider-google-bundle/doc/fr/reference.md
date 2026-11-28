<!-- phil-dev source-sha256=a743d2e42834ec6415c070928b8760b7180dae0e4a93b53f0e3836b08b3b4b9f -->
# Référence

Chaque type public du paquet, avec son rôle.

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Risc\SecurityEventType` | Les événements de sécurité lus, par leur URI, et si chacun ferme les sessions. |
| `Domain\Risc\SecurityEvent` | Un événement : son type, l'émetteur et l'identifiant de son sujet, son `reason` et son `state`. |
| `Domain\Risc\SecurityEventToken` | Les revendications d'un jeton d'événement de sécurité, lues dans des bornes : émetteur, identifiant, heure d'émission, audiences, événements. |
| `Domain\Exception\InvalidSecurityEventToken` | Un jeton refusé, avec son code d'erreur de la RFC 8935. |

## Application

| Type | Rôle |
|---|---|
| `Application\GoogleSettings` | L'identifiant client, les domaines Workspace acceptés, le profil, le `prompt`, le décalage d'horloge et la durée de vie d'une connexion ; le nom, le libellé, l'émetteur et l'adresse de découverte de Google. |
| `Application\GoogleSignIn` | Le flux du code d'autorisation : `start()` donne l'adresse de Google, `finish()` l'identité. |
| `Application\GoogleClaims` | Les revendications d'un jeton d'identité vérifié transposées en `ExternalIdentity` du bundle. |
| `Application\SignInAttempts`, `Application\SignInAttempt` | Les connexions qui attendent dans la session, cinq au plus, chacune prise une seule fois. |
| `Application\TransmitterSettings` | Un émetteur d'événements de sécurité et ce que le récepteur en accepte ; `google()` est celui de Google. |
| `Application\SecurityEventTokenReader` | Vérifie la signature, l'émetteur, l'audience et l'âge d'un jeton d'événement de sécurité. |
| `Application\SecurityEventReceiver` | Agit sur les événements d'un jeton : sessions fermées, sujets retenus ou libérés, événements envoyés. |
| `Application\Receipt` | Ce qui est résulté d'un jeton : reçu auparavant, événements, sessions fermées. |
| `Application\ReplayGuard` | Les identifiants des jetons sur lesquels on a agi, dans un bassin PSR-6. |
| `Application\LinkedSessions` | Le compte auquel un sujet est lié, et la fermeture de ses sessions par le bundle. |
| `Application\Contract\SubjectHolds` | Le port des sujets retenus. |
| `Application\Event\SecurityEventReceived` | L'événement envoyé une fois par événement de sécurité sur lequel on a agi. |
| `Application\Exception\TransmitterUnreachable` | Les clés de l'émetteur n'ont pas pu être lues : le point de terminaison répond 503. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Federation\GoogleProvider` | L'`ExternalProvider` du bundle pour Google, portant l'étiquette `phil_identity_provider.external_provider`. |
| `Infrastructure\Http\RiscController` | Le point de terminaison de réception des événements de sécurité. |
| `Infrastructure\Http\SafeHttpTransport` | L'`HttpClientInterface` de Symfony sur phil/safe-http, pour les pièces OpenID Connect de Symfony. |
| `Infrastructure\Http\SafeHttpResponse`, `Infrastructure\Http\ReadChunk` | Une réponse lue par phil/safe-http, et ses fragments, comme le contrat de Symfony les donne. |
| `Infrastructure\Cache\CacheSubjectHolds` | Les sujets retenus dans un bassin PSR-6. |
| `Infrastructure\DependencyInjection\GoogleServices` | Les services, à partir de la configuration. |
| `PhilIdentityProviderGoogleBundle` | Le bundle et son arbre de configuration. |

## Routes

| Nom | Chemin | Méthode |
|---|---|---|
| `phil_identity_provider_google_risc` | `/risc`, sous le préfixe de l'import | `POST` |

La connexion utilise les routes de phil/identity-provider-bundle : `phil_identity_provider_external_start`
(`/login/with/{provider}`) et `phil_identity_provider_external_callback`.

## Services

| Service | Ce que c'est |
|---|---|
| `Phil\IdentityProviderGoogleBundle\Infrastructure\Federation\GoogleProvider` | Le fournisseur, étiqueté pour le bundle. |
| `Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn` | Le flux. |
| `Phil\IdentityProviderGoogleBundle\Application\GoogleSettings` | Les réglages. |
| `phil_identity_provider_google.transport` | Le client HTTP des pièces OpenID Connect de Symfony, sur phil/safe-http. |
| `phil_identity_provider_google.discovery`, `phil_identity_provider_google.signatures` | `OidcDiscovery` et `OidcSignatureVerifier` de Symfony pour la connexion avec Google. |
| `phil_identity_provider_google.risc_discovery`, `phil_identity_provider_google.risc_signatures` | Les mêmes pour les événements de sécurité, avec `risc.enabled`. |
| `Phil\IdentityProviderGoogleBundle\Application\SecurityEventReceiver`, `Phil\IdentityProviderGoogleBundle\Application\TransmitterSettings` | Le récepteur et l'émetteur de Google, avec `risc.enabled`. |
| `Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds` | Un alias de `CacheSubjectHolds`, avec `risc.enabled` et `risc.hold_hijacked`. |
| `Phil\IdentityProviderGoogleBundle\Infrastructure\Http\RiscController` | Le point de terminaison. |
