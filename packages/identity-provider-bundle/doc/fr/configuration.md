<!-- phil-dev source-sha256=13ca7faecf4a2c1aa17d3e99291299f02247e1bb395cc607e07f70cb1365d6c6 -->
# Configuration

La configuration du bundle se trouve sous `phil_identity_provider`. Chaque option a une valeur par défaut, sauf les
quelques-unes qu'exige un module : `passkeys.rp_id` et `passkeys.origins` pour les clés d'accès, `oidc.issuer` pour le
fournisseur.

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    secret: '%env(IDENTITY_SECRET)%'
    site_name: Example
    modules:
        accounts: true          # always on
        password: true
        passkeys: true
        totp: true
        recovery_codes: true
        login_link: false
        sms: false
        sessions: true
        step_up: true
        external: false
        oidc: true
    login_route: app_login
    default_target: /
    open_routes: [app_logout]
    mailer: { from: accounts@example.com }
    password: { min_score: 2, breach_check: true }
    lockout: { threshold: 5, base: 60, max: 3600 }
    passkeys: { rp_id: example.com, origins: ['https://example.com'] }
    oidc: { issuer: 'https://example.com' }
    secrets: { kms_key: identity }
```

## Options communes

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `secret` | `%kernel.secret%` | Signe les liens envoyés par courriel et sert de clé aux codes de récupération ; au moins 32 caractères, tiré du coffre à secrets. Le changer invalide tous les liens et tous les codes de récupération. |
| `site_name` | `PHIL` | Le nom dans les courriels, dans l'application d'authentification et sur les clés d'accès. |
| `store` | `doctrine` | `doctrine`, ou `memory` pour les tests : vidé entre les requêtes. |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine des tables. |
| `clock` | `clock` | Identifiant de service de l'horloge PSR-20. |
| `layout` | `@PhilIdentityProvider/layout.html.twig` | Le gabarit que les pages étendent. |
| `kit_subdirectory` | `null` | L'emplacement des recettes du kit ; le sous-répertoire de `ux_toolkit` quand la valeur est nulle. |
| `registration` | `true` | Si n'importe qui peut s'inscrire. |
| `require_verified_email` | `false` | Si un compte ne se connecte qu'une fois son adresse vérifiée. |
| `login_route` | `null` | La route du formulaire de connexion, proposée pour une réauthentification par mot de passe et après l'échec d'une connexion externe. |
| `default_target` | `/` | Où mène une connexion par les authentificateurs du bundle quand rien n'a demandé de page. |
| `open_routes` | `[]` | Les routes joignables avant la deuxième étape d'une connexion, par leur nom exact, comme la route de déconnexion ; en développement, `_wdt` et `_profiler` si la barre d'outils doit s'afficher. À côté d'elles, seuls le défi, le code qu'il envoie et la connexion par clé d'accès sont ouverts. |
| `mailer.from` | `null` | L'expéditeur des courriels ; sans lui, rien n'est envoyé et les événements le sont quand même. |
| `audit` | `auto` | `auto` écrit dans `phil/audit-bundle` quand il est installé, `none` nulle part, ou l'identifiant d'un `AuditRecorder`. |
| `secrets.kms_key` | `null` | La clé du composant KeyManagement qui chiffre les secrets TOTP et les clés privées de signature. Avec les modules `totp` ou `oidc`, nulle, elle est refusée sauf si `secrets.allow_plaintext_secrets` vaut true ; une clé sans `symfony/key-management` est refusée. |
| `secrets.allow_plaintext_secrets` | `false` | Si les secrets TOTP et les clés privées de signature peuvent être gardés en clair quand aucune clé n'est réglée, comme dans les tests ; les contrôles d'accès de la base sont alors leur seule protection. |
| `secrets.encrypter`, `secrets.decrypter` | `key_management.encrypter`, `key_management.decrypter` | Les services du composant KeyManagement. |

## Modules

| Module | Valeur par défaut | Exige | Tables |
|---|---|---|---|
| `accounts` | activé, ne se désactive pas | — | `phil_identity_account` |
| `password` | activé | — | — |
| `passkeys` | désactivé | `passkeys.rp_id`, `passkeys.origins` | `phil_identity_passkey` |
| `totp` | désactivé | — | `phil_identity_totp`, `phil_identity_recovery_codes` |
| `recovery_codes` | désactivé | `totp` | — |
| `login_link` | désactivé | le `login_link` du pare-feu | — |
| `sms` | désactivé | le composant Notifier ou `sms.sender` | — |
| `sessions` | activé | — | `phil_identity_session` |
| `step_up` | activé | le `re_authentication_entry_point` du pare-feu | — |
| `external` | désactivé | un paquet qui implémente `ExternalProvider` | `phil_identity_linked` |
| `oidc` | désactivé | `oidc.issuer` | `phil_identity_client`, `phil_identity_consent`, `phil_identity_code`, `phil_identity_token`, `phil_identity_signing_key` |

## Options de chaque module

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `password.min_score` | `2` | Le plus bas score de l'estimateur de force de `phil/passwords`, de 0 à 4. |
| `password.breach_check` | `false` | Si un nouveau mot de passe est cherché dans Pwned Passwords, par k-anonymat : seuls cinq caractères de son SHA-1 quittent le serveur. |
| `password.http_client` | `http_client` | Le client HTTP de la vérification des fuites. |
| `lockout.threshold` | `5` | Échecs de connexion par mot de passe de suite avant le premier verrou. |
| `lockout.base` | `60` | Secondes du premier verrou, doublées à chaque échec de plus. |
| `lockout.max` | `3600` | Le plus long verrou. |
| `login_limiter.enabled` | `false` | Enregistre `phil_identity_provider.login_limiter`, le limiteur que le `login_throttling` du pare-feu nomme par son option `limiter` ; demande `symfony/rate-limiter`. Voir [Limiter les connexions](accounts.md#limiter-les-connexions). |
| `login_limiter.max_attempts` | `5` | Les connexions par identifiant et adresse à chaque intervalle. |
| `login_limiter.network_max_attempts` | `25` | Les connexions par adresse, ou réseau IPv6 /64, à chaque intervalle. |
| `login_limiter.account_max_attempts` | `50` | Les connexions par identifiant à chaque intervalle, quelle que soit l'adresse. Quiconque connaît l'identifiant peut le dépenser. |
| `login_limiter.interval` | `15 minutes` | La fenêtre des comptes. |
| `login_limiter.cache_pool` | `cache.rate_limiter` | La réserve de cache des comptes, partagée par tous les serveurs : dans l'environnement `prod`, une réserve locale à chaque serveur fait échouer la construction du conteneur. |
| `login_limiter.lock_factory` | `null` | Une `LockFactory` sur un stockage partagé, pour que deux connexions au même moment comptent toutes les deux. |
| `login_limiter.single_server` | `false` | Accepte dans l'environnement `prod` une réserve de cache locale au serveur, pour une application sur un seul serveur. |
| `links.verify_lifetime` | `86400` | Secondes de validité d'un lien qui vérifie une adresse. |
| `links.reset_lifetime` | `3600` | Secondes de validité d'un lien qui réinitialise un mot de passe. |
| `passkeys.rp_id` | `null` | L'identifiant de la partie de confiance : le domaine enregistrable du site, comme `example.com`. |
| `passkeys.rp_name` | `null` | Le nom que montre l'authentificateur ; le nom du site quand la valeur est nulle. |
| `passkeys.origins` | `[]` | Les origines d'où les pages sont servies, comme `https://example.com`. |
| `passkeys.require_user_verification` | `false` | Si une clé d'accès doit vérifier la personne par un NIP ou une donnée biométrique. |
| `totp.issuer` | `null` | L'émetteur que montre l'application d'authentification ; le nom du site quand la valeur est nulle. |
| `totp.window` | `1` | Les périodes de 30 secondes acceptées avant et après la période courante, de 0 à 10. |
| `sms.sender` | `null` | Identifiant de service d'un `SmsSender` ; le texteur du composant Notifier quand la valeur est nulle. |
| `sms.from` | `''` | L'expéditeur des messages texte, quand le transport en permet un. |
| `sms.message` | `Your code: {code}` | Le texte, `{code}` étant remplacé par le code. |
| `sms.lifetime` | `600` | Secondes de validité d'un code. |
| `sms.attempts` | `5` | Essais qu'un code permet. |
| `step_up.attribute` | `PHIL_STEP_UP` | L'attribut qu'exigent les actions sensibles du bundle : `PHIL_STEP_UP`, ou `IS_AUTHENTICATED_RECENTLY` de Symfony Security. |
| `step_up.lifetime` | `300` | Secondes pendant lesquelles une preuve accorde `PHIL_STEP_UP`. |
| `sessions.touch_interval` | `300` | Le moins de secondes entre deux écritures du moment où une session a été vue pour la dernière fois. |
| `external.link_verified_email` | `false` | Si une identité dont le fournisseur a vérifié l'adresse se lie au compte qui a vérifié la même adresse, au lieu de demander à la personne de se connecter d'abord. |
| `external.register` | `true` | Si une identité externe inconnue crée un compte. |
| `oidc.issuer` | `null` | L'adresse https sous laquelle les clients connaissent le fournisseur, sans barre oblique finale. |
| `oidc.algorithm` | `RS256` | L'algorithme des nouvelles clés de signature : `RS256`, `ES256` ou `EdDSA`. |
| `oidc.code_lifetime` | `60` | Secondes de validité d'un code d'autorisation, 600 au plus. |
| `oidc.access_token_lifetime` | `600` | Secondes de validité d'un jeton d'accès. |
| `oidc.id_token_lifetime` | `600` | Secondes de validité d'un jeton d'identité. |
| `oidc.refresh_token_lifetime` | `2592000` | Secondes de validité d'un jeton d'actualisation. |
| `oidc.key_activation_delay` | `86400` | Secondes pendant lesquelles une nouvelle clé est publiée avant de signer. |
| `oidc.retired_key_retention` | `604800` | Secondes pendant lesquelles une clé retirée reste publiée. |
| `oidc.allow_localhost` | `false` | Si les adresses de redirection sur `localhost` en http sont acceptées, pour le développement. |
| `oidc.lock_factory` | `lock.factory` | Le service `LockFactory` qui sérialise la première clé de signature et chaque rotation ; son magasin doit être partagé par tous les serveurs. |

## Un site qui refuse certaines méthodes

Un site qui n'offre ni liens de connexion ni messages texte laisse `login_link` et `sms` désactivés : leurs routes
n'existent pas, leurs contrôleurs ne sont pas enregistrés et aucune page n'y mène.
