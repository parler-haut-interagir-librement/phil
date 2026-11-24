<!-- phil-dev source-sha256=70fdc8644dfcae6665749f79978d16967a4d6986541071b8021597fb39a70f4f -->
# Conformité OpenID

L'OpenID Foundation publie une suite de conformité qui joue un client contre un fournisseur et vérifie chaque
comportement des spécifications. `tests/Conformance` l'exécute dans Docker contre le fournisseur de ce bundle, à partir de
rien, avec une seule commande, et échoue à chaque échec et à chaque avertissement ou omission qu'il n'explique pas.

## Les profils

| Plan de la suite | Profil | Joué |
|---|---|---|
| `oidcc-basic-certification-test-plan`, découverte, clients statiques | Basic OP : le flux du code d'autorisation, userinfo, `prompt`, `max_age`, la réutilisation du code, les jetons d'actualisation et PKCE (`oidcc-ensure-request-with-valid-pkce-succeeds`) | Oui |
| `oidcc-config-certification-test-plan` | Config OP : le document de découverte et le jeu de clés | Oui |
| `oidcc-rp-initiated-logout-certification-test-plan`, `code`, clients statiques | RP-Initiated Logout OP | Oui |
| `oidcc-dynamic-certification-test-plan` | Dynamic OP | Non : il enregistre ses clients par l'enregistrement dynamique de clients, que le fournisseur n'offre pas, et joue le flux hybride (`code id_token`), qu'il refuse. |
| Implicite, hybride, form post, déconnexion par canal avant et arrière, connexion à l'initiative d'un tiers | — | Non : le fournisseur répond au flux du code dans le mode de réponse `query` seulement, et n'offre ni ces canaux de déconnexion ni cette connexion. |

La suite n'a pas de plan à elle pour PKCE chez un fournisseur hors des profils FAPI : le plan Basic joue une requête avec
un défi S256 valide, et les tests unitaires et fonctionnels du bundle couvrent les refus — un défi manquant, `plain` ou
de forme invalide, un vérificateur erroné, un vérificateur pour un code émis sans défi.

## L'exécuter

```sh
packages/identity-provider-bundle/tests/Conformance/run.sh        # from the monorepo
packages/identity-provider-bundle/tests/Conformance/run.sh down   # stops and removes the containers
```

Docker, `git` et `curl` sont nécessaires, et le port 8443 de l'hôte doit être libre. `run.sh` :

1. récupère depuis son dépôt les scripts qui pilotent la suite, à la version de ses images
   (`PHIL_CONFORMANCE_SUITE_VERSION`, `release-v5.3.4` par défaut), dans `var/conformance/suite` ;
2. démarre `compose.yaml` : MongoDB, le serveur de la suite et nginx à partir de leurs images publiées, et le
   fournisseur — l'application de `App/ConformanceKernel.php`, servie par FrankenPHP avec un certificat à elle à
   `https://op.phil.test` —, le monodépôt monté en lecture seule ;
3. exécute `bin/setup.php` dans le fournisseur : des tables neuves sur SQLite, une première clé de signature, un compte
   d'essai et deux clients confidentiels enregistrés avec `--pkce-optional`, puisque le profil Basic joue des clients
   sans PKCE, tous avec des secrets générés pour cette exécution — l'application règle
   `oidc.require_nonce_without_pkce: false`, puisque `oidcc-ensure-request-without-nonce-succeeds-for-code-flow` joue un
   tel client sans `nonce` ; il écrit la configuration des plans, avec les étapes
   que le navigateur de la suite suit sur les pages de connexion, de réauthentification, de consentement et de
   déconnexion ;
4. joue les trois plans et exporte le journal de chacun dans `var/conformance/results`, un zip par plan.

Les pages de la suite restent à `https://localhost.emobix.co.uk:8443` pendant que les conteneurs tournent, pour regarder
chaque module. `expected-warnings.json` et `expected-skips.json` expliquent les avertissements et les omissions
ci-dessous ; tout le reste fait échouer l'exécution.

Les modules qui se terminent par `REVIEW` ont réussi chaque vérification et attendent qu'une personne regarde la capture
d'écran que le navigateur a prise d'une page que le fournisseur a montrée : la page de réauthentification de
`prompt=login` et de `max_age=1`, la page d'une demande refusée, la confirmation ou la fin d'une déconnexion. Ils sont
revus à la main avant une certification.

## Résultats

Exécutés le 2026-11-15 avec la suite `release-v5.3.4`, PHP 8.5, Symfony 8.2, `web-token/jwt-library` 4.2, clés RS256.
Aucun module n'a échoué.

### Basic OP

| Module de test | Résultat | Succès | Échecs | Avertissements |
|---|---|---|---|---|
| `oidcc-server` | RÉUSSI | 53 | 0 | 0 |
| `oidcc-response-type-missing` | RÉUSSI | 31 | 0 | 0 |
| `oidcc-userinfo-get` | RÉUSSI | 52 | 0 | 0 |
| `oidcc-userinfo-post-header` | RÉUSSI | 53 | 0 | 0 |
| `oidcc-userinfo-post-body` | RÉUSSI | 51 | 0 | 0 |
| `oidcc-ensure-request-without-nonce-succeeds-for-code-flow` | RÉUSSI | 27 | 0 | 0 |
| `oidcc-scope-profile` | AVERTISSEMENT | 53 | 0 | 1 |
| `oidcc-scope-email` | RÉUSSI | 57 | 0 | 0 |
| `oidcc-scope-address` | IGNORÉ | 15 | 0 | 0 |
| `oidcc-scope-phone` | RÉUSSI | 54 | 0 | 0 |
| `oidcc-scope-all` | IGNORÉ | 15 | 0 | 0 |
| `oidcc-alternate-happy-flow` | RÉUSSI | 57 | 0 | 0 |
| `oidcc-display-page` | RÉUSSI | 46 | 0 | 0 |
| `oidcc-display-popup` | RÉUSSI | 46 | 0 | 0 |
| `oidcc-prompt-login` | À REVOIR | 80 | 0 | 0 |
| `oidcc-prompt-none-not-logged-in` | RÉUSSI | 33 | 0 | 0 |
| `oidcc-prompt-none-logged-in` | RÉUSSI | 79 | 0 | 0 |
| `oidcc-max-age-1` | À REVOIR | 82 | 0 | 0 |
| `oidcc-max-age-10000` | RÉUSSI | 82 | 0 | 0 |
| `oidcc-ensure-request-with-unknown-parameter-succeeds` | RÉUSSI | 46 | 0 | 0 |
| `oidcc-id-token-hint` | RÉUSSI | 80 | 0 | 0 |
| `oidcc-login-hint` | RÉUSSI | 47 | 0 | 0 |
| `oidcc-ui-locales` | RÉUSSI | 47 | 0 | 0 |
| `oidcc-claims-locales` | RÉUSSI | 47 | 0 | 0 |
| `oidcc-ensure-request-with-acr-values-succeeds` | AVERTISSEMENT | 47 | 0 | 1 |
| `oidcc-codereuse` | RÉUSSI | 54 | 0 | 0 |
| `oidcc-codereuse-30seconds` | RÉUSSI | 58 | 0 | 0 |
| `oidcc-ensure-registered-redirect-uri` | À REVOIR | 22 | 0 | 0 |
| `oidcc-ensure-post-request-succeeds` | RÉUSSI | 46 | 0 | 0 |
| `oidcc-server-client-secret-post` | RÉUSSI | 45 | 0 | 0 |
| `oidcc-unsigned-request-object-supported-correctly-or-rejected-as-unsupported` | IGNORÉ | 25 | 0 | 0 |
| `oidcc-claims-essential` | AVERTISSEMENT | 55 | 0 | 1 |
| `oidcc-ensure-request-object-with-redirect-uri` | À REVOIR | 24 | 0 | 0 |
| `oidcc-refresh-token` | RÉUSSI | 138 | 0 | 0 |
| `oidcc-ensure-request-with-valid-pkce-succeeds` | RÉUSSI | 47 | 0 | 0 |

Les trois avertissements, tous sur un `SHOULD` ou sur ce que contiennent les comptes :

- `oidcc-scope-profile` : un compte a un nom, pas les autres revendications de la portée `profile` (`given_name`,
  `family_name`, `picture`…) ;
- `oidcc-ensure-request-with-acr-values-succeeds` : le jeton d'identité énonce les méthodes prouvées dans `amr` et ne
  nomme aucune classe de contexte d'authentification dans `acr` ;
- `oidcc-claims-essential` : le paramètre `claims` n'est pas pris en charge (`claims_parameter_supported: false`), de
  sorte que `name` n'est retourné que pour la portée `profile`.

Les trois omissions : le fournisseur ne connaît aucune portée `address` (`oidcc-scope-address`, `oidcc-scope-all`), et il
refuse les objets de requête avec `request_not_supported`, ce que la suite accepte (`oidcc-unsigned-request-object-…`).

### Config OP

| Module de test | Résultat | Succès | Échecs | Avertissements |
|---|---|---|---|---|
| `oidcc-discovery-endpoint-verification` | RÉUSSI | 34 | 0 | 0 |

### RP-Initiated Logout OP

| Module de test | Résultat | Succès | Échecs | Avertissements |
|---|---|---|---|---|
| `oidcc-rp-initiated-logout-discovery-endpoint-verification` | RÉUSSI | 8 | 0 | 0 |
| `oidcc-rp-initiated-logout` | RÉUSSI | 68 | 0 | 0 |
| `oidcc-rp-initiated-logout-bad-post-logout-redirect-uri` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-modified-id-token-hint` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-id-token-hint` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-params` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-post-logout-redirect-uri` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-state` | RÉUSSI | 69 | 0 | 0 |
| `oidcc-rp-initiated-logout-only-state` | À REVOIR | 51 | 0 | 0 |
| `oidcc-rp-initiated-logout-query-added-to-post-logout-redirect-uri` | À REVOIR | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-bad-id-token-hint` | À REVOIR | 53 | 0 | 0 |

## Certification

La certification par l'OpenID Foundation est une étape à part : elle exige le fournisseur à une adresse publique, les
journaux exportés par l'instance hébergée de la suite et les captures d'écran des modules `REVIEW`. Elle précède
l'utilisation du fournisseur en production.
