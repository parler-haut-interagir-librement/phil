<!-- phil-dev source-sha256=ef0d0576122b15cf452decefb5a8fd420a16b81b8b1564473117b1d75b83f8f9 -->
# Sécurité

## Ce que le bundle garantit

| Menace | Protection | Où |
|---|---|---|
| Jeton d'identité falsifié ou signé à nouveau, `alg: none`, clé publique utilisée comme secret partagé | Un seul algorithme asymétrique fixé, clés prises seulement dans le jeu de clés du fournisseur | `oidc_login`, `signature_algorithm` |
| Clés intégrées à l'en-tête d'un jeton (`jwk`, `jku`, `x5u`) | Jamais lues | `oidc_login` |
| Réponse d'autorisation rejouée ou étrangère | `state`, `nonce` et PKCE | `oidc_login` |
| Un bloc de pare-feu qui désactive PKCE ou la vérification de la signature du jeton d'identité | Construction refusée | `NativeAuthenticatorPass` |
| Fixation de session | L'identifiant de session change à la connexion ; `session_fixation_strategy: none` refusé à la construction | `FirewallComposer` |
| Prise de contrôle d'un compte par une adresse courriel | Identité indexée sur `sub`, adresses non vérifiées retirées | `VerifiedIdentity` |
| Redirection ouverte après la connexion | `return_to` lu avec l'analyseur du navigateur, puis reconstruit | `DeclaredOriginsReturnToPolicy` |
| Jeton de déconnexion falsifié qui met fin à des sessions | Signature et revendications vérifiées | Règles de `OidcBackChannelLogout` |
| Jeton de déconnexion rejoué | Chaque `jti` une seule fois | `ReplayGuardedBackChannelLogout` |
| Jeton de déconnexion qui n'arrive jamais | Plafond de session | `SessionCapListener` |
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

Les paramètres `_failure_path` et `_target_path` des gestionnaires de Symfony restent lisibles au retour. Symfony ne
redirige vers eux que sur l'hôte de la requête ou dans le domaine du témoin de session, et une connexion réussie exige
le `state` de la session du navigateur lui-même : ils ne peuvent donc pas mener un utilisateur vers un autre site.

Un jeton de déconnexion se distingue d'un jeton d'identité par sa revendication `events` et l'absence de `nonce`, comme
l'exige OpenID Connect Back-Channel Logout 1.0 ; son en-tête `typ` n'est pas vérifié, puisque les fournisseurs
n'envoient pas tous `logout+jwt`.

Signalez une vulnérabilité comme le décrit [SECURITY.md](../../SECURITY.md).
