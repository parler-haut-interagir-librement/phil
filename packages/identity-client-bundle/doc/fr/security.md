<!-- phil-dev source-sha256=454730e1dd17deba165111afc0ef5513c270193c956aa3d72c65511e8b0fd5cd -->
# Sécurité

## Ce que le bundle garantit

| Menace | Protection | Où |
|---|---|---|
| Jeton d'identité falsifié ou signé à nouveau, `alg: none`, clé publique utilisée comme secret partagé | Un seul algorithme asymétrique fixé, clés prises seulement dans le jeu de clés du fournisseur | `oidc_login`, `signature_algorithm` |
| Clés intégrées à l'en-tête d'un jeton (`jwk`, `jku`, `x5u`) | Jamais lues | `oidc_login` |
| Réponse d'autorisation rejouée ou étrangère | `state`, `nonce` et PKCE | `oidc_login` |
| Confusion de fournisseur : un code d'un fournisseur envoyé à un autre | L'`iss` de la réponse d'autorisation (RFC 9207) comparé à l'émetteur du document de découverte, et exigé quand le fournisseur annonce `authorization_response_iss_parameter_supported` | `oidc_login` |
| Un bloc de pare-feu qui désactive PKCE ou la vérification de la signature du jeton d'identité | Construction refusée | `NativeAuthenticatorPass` |
| Fixation de session | L'identifiant de session change à la connexion ; `session_fixation_strategy: none` refusé à la construction | `FirewallComposer` |
| Prise de contrôle d'un compte par une adresse courriel | Identité indexée sur `sub`, adresses non vérifiées retirées | `VerifiedIdentity` |
| Redirection ouverte après la connexion | `return_to` lu avec l'analyseur du navigateur, puis reconstruit | `DeclaredOriginsReturnToPolicy` |
| Jeton de déconnexion falsifié qui met fin à des sessions | Signature et revendications vérifiées | Règles de `OidcBackChannelLogout` |
| Jeton de déconnexion rejoué | Chaque `jti` une seule fois | `ReplayGuardedBackChannelLogout` |
| Jeton de déconnexion qui n'arrive jamais | Plafond de session | `SessionCapListener` |
| Réserve de rejeu ou sessions terminées perdues avec un cache vidé | Jetons de déconnexion et sessions plus anciens que le plancher de démarrage à froid refusés, une fois activé | `CacheReplayFloor`, `SessionRequirementsListener` |
| Un fournisseur qui répond avec un `acr` plus faible que celui demandé | L'`acr` retourné vérifié contre le niveau exigé, sessions sous ce niveau terminées | `SignInListener`, `SessionRequirementsListener` |
| Famille de jetons de renouvellement révoquée par une course | Un renouvellement par jeton, résultat partagé | `SerializedTokenRefreshListener` |
| Famille de jetons de renouvellement révoquée par le fournisseur | Toute la session locale invalidée | `SerializedTokenRefreshListener` |
| Jetons divulgués par une session ou un cache | Gardés seulement s'ils sont lus ; résultats partagés chiffrés | `SignInListener`, `EncryptedRefreshOutcomes` |

## Ce qui reste à l'application

- Servir en HTTPS, avec des témoins de session `Secure`.
- Partager le magasin de verrous et les réserves de cache entre les serveurs ; voir [Installation](installation.md).
- Faire confiance aux bons hôtes, puisque les redirections locales sont construites à partir de la requête.
- Limiter le débit du point de terminaison de déconnexion par canal arrière en bordure. Chaque envoi coûte au plus une
  vérification de signature, et le jeu de clés est mis en cache.
- Un `POST` avec un jeton CSRF pour la déconnexion.

## Limites

Le bundle s'appuie sur des identifiants de service du bundle de sécurité pour `oidc_login`, introduits par la version
`8.2` ; une version mineure ultérieure peut les déplacer. `NativeAuthenticatorPass` arrête alors la construction et nomme le
service manquant, de sorte que le changement est découvert à la construction de l'application plutôt qu'à une
connexion.

L'`iss` de la réponse d'autorisation est vérifié par l'authentificateur `oidc_login` de Symfony 8.2 lui-même, avant le
paramètre `error` et avant l'échange du code : un `iss` différent de l'émetteur du document de découverte, comparé octet
par octet, est refusé, tout comme une réponse qui en est dépourvue de la part d'un fournisseur dont le document de
découverte annonce `authorization_response_iss_parameter_supported` ; un fournisseur qui ne l'annonce pas peut l'omettre.
Le bundle n'ajoute aucune vérification de son cru, et ses tests fonctionnels prouvent ce comportement à travers le
pare-feu qu'il compose, de sorte qu'un changement de l'authentificateur les fait échouer.

Les paramètres `_failure_path` et `_target_path` des gestionnaires de Symfony restent lisibles au retour. Symfony ne
redirige vers eux que sur l'hôte de la requête ou dans le domaine du témoin de session, et une connexion réussie exige
le `state` de la session du navigateur lui-même : ils ne peuvent donc pas mener un utilisateur vers un autre site.

Un jeton de déconnexion se distingue d'un jeton d'identité par sa revendication `events` et l'absence de `nonce`, comme
l'exige OpenID Connect Back-Channel Logout 1.0 ; son en-tête `typ` n'est pas vérifié, puisque les fournisseurs
n'envoient pas tous `logout+jwt`.

Signalez une vulnérabilité comme le décrit [SECURITY.md](../../SECURITY.md).
