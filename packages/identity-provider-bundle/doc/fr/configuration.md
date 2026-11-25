<!-- phil-dev source-sha256=b43cfb0ccda7fad3cc54b811f1661ccab8ae621016a2da266b7c67ad1a5f8116 -->
# Configuration

La configuration du bundle se trouve sous `phil_identity_provider`. Chaque option a une valeur par défaut, sauf les
quelques-unes qu'exige un module : `passkeys.rp_id` et `passkeys.origins` pour les clés d'accès, `oidc.issuer` pour le
fournisseur.

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    secret: '%env(IDENTITY_SECRET)%'
    site_name: Example
    public_origin: 'https://example.com'
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
    accounts: { id_generator: ulid }
    lockout: { threshold: 5, base: 60, max: 3600 }
    links: { mode: signed }
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
| `public_origin` | `null` | L'adresse canonique du site, comme `https://example.com`, sur laquelle mènent les liens envoyés par courriel. Nulle, elle prend l'hôte de la requête, que `framework.trusted_hosts` doit alors borner : dans l'environnement `prod`, le bundle refuse de démarrer quand ni l'un ni l'autre n'est réglé. Voir [Où mènent les liens](accounts.md#où-mènent-les-liens). |
| `accounts.id_generator` | `ulid` | Ce qui donne à un nouveau compte son identifiant interne : `ulid`, `uuid_v7`, ou l'id d'un service qui implémente `AccountIdGenerator`. Voir [Identifiants](accounts.md#identifiants). |
| `accounts.strip_subaddress` | `false` | Si la forme canonique d'une adresse omet son sous-adressage, `+news` dans `ana+news@example.com`, pour qu'une boîte de courriel ne tienne qu'un compte. Voir [Adresses](accounts.md#adresses). |
| `accounts.canonicalizer` | `null` | L'id d'un service qui implémente `AddressCanonicalizer`, à la place de celui du bundle ; `strip_subaddress` est alors le sien. |
| `registration.enabled` | `true` | Si n'importe qui peut s'inscrire ; `registration: false` la ferme. |
| `registration.rate_limit.enabled` | `true` | Limite l'inscription et les courriels de vérification ; `rate_limit: false` désactive les limites. Voir [Limiter les inscriptions](accounts.md#limiter-les-inscriptions). |
| `registration.rate_limit.per_ip.limit`, `.interval` | `10`, `1 hour` | Les inscriptions par adresse de client, ou par réseau IPv6 /64, à chaque intervalle. |
| `registration.rate_limit.per_address.limit`, `.interval` | `5`, `1 hour` | Les inscriptions par adresse saisie, connue ou non, à chaque intervalle. |
| `registration.rate_limit.resend_delay` | `5 minutes` | Le délai avant qu'un autre courriel de vérification aille à la même adresse. |
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
| `accounts` | activé, ne se désactive pas | — | `phil_identity_account`, `phil_identity_address` ; `phil_identity_link_token` avec `links.mode: stored` |
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
| `password.semaphore.enabled` | `true` | Plafonne les empreintes et vérifications de mots de passe des comptes calculées en même temps sur chaque serveur ; `semaphore: false` désactive le plafond. Voir [Le plafond des vérifications de mot de passe](accounts.md#le-plafond-des-vérifications-de-mot-de-passe). |
| `password.semaphore.slots` | `4` | Combien d'empreintes s'exécutent en même temps, de 1 à 256 : environ le nombre de cœurs du processeur. |
| `password.semaphore.wait` | `3.0` | Combien de temps une empreinte attend une place, en secondes, avant que la connexion soit refusée avec `password.busy`. |
| `password.semaphore.lock_factory` | `null` | Un service `LockFactory` sur un magasin local à chaque serveur ; null garde des fichiers flock dans le répertoire temporaire. |
| `lockout.enabled` | `true` | Si les échecs de connexion par mot de passe verrouillent le compte ; `lockout: false` les compte sans rien verrouiller et laisse les essais à `login_limiter`. Voir [Le verrou](accounts.md#le-verrou). |
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
| `links.mode` | `signed` | `signed` : rien n'est conservé, un lien fonctionne jusqu'à son expiration ou jusqu'à ce que son compte change ; `stored` : le lien porte un jeton aléatoire dont l'empreinte à clé est conservée, servi une fois et révocable. Voir [Liens conservés](accounts.md#liens-conservés). |
| `passkeys.rp_id` | `null` | L'identifiant de la partie de confiance : le domaine enregistrable du site, comme `example.com`. |
| `passkeys.rp_name` | `null` | Le nom que montre l'authentificateur ; le nom du site quand la valeur est nulle. |
| `passkeys.origins` | `[]` | Les origines d'où les pages sont servies, comme `https://example.com`. |
| `passkeys.require_user_verification` | `false` | Si une clé d'accès doit vérifier la personne par un NIP ou une donnée biométrique. |
| `passkeys.attestation.conveyance` | `none` | L'attestation demandée au navigateur : `none` ne lit aucune déclaration ; `indirect`, `direct` ou `enterprise` lisent les déclarations du format `packed`. Voir [Attestation](passkeys.md#attestation). |
| `passkeys.attestation.trusted_roots` | `[]` | Les fichiers PEM des certificats racines auxquels une déclaration `packed` peut mener, lus à la construction du conteneur. |
| `passkeys.attestation.require_trusted` | `false` | Si une clé d'accès dont la déclaration n'atteint aucune racine de confiance est refusée ; exige `trusted_roots` et une transmission autre que `none`. |
| `passkeys.attestation.allowed_aaguids` | `[]` | Les AAGUID acceptés, vide pour n'importe lequel. |
| `passkeys.attestation.denied_aaguids` | `[]` | Les AAGUID refusés. |
| `passkeys.attestation.backup` | `allowed` | Si une clé d'accès qui peut être synchronisée avec d'autres appareils est `allowed` (permise), `required` (exigée) ou `forbidden` (interdite). |
| `totp.issuer` | `null` | L'émetteur que montre l'application d'authentification ; le nom du site quand la valeur est nulle. |
| `totp.window` | `1` | Les périodes de 30 secondes acceptées avant et après la période courante, de 0 à 10. |
| `sms.sender` | `null` | Identifiant de service d'un `SmsSender` ; un texteur sur les transports SMS du composant Notifier, sans bus, quand la valeur est nulle. |
| `sms.from` | `''` | L'expéditeur des messages texte, quand le transport en permet un. |
| `sms.message` | `Your code: {code}` | Le texte, `{code}` étant remplacé par le code. |
| `sms.lifetime` | `600` | Secondes de validité d'un code. |
| `sms.attempts` | `5` | Essais qu'un code permet. |
| `step_up.attribute` | `PHIL_STEP_UP` | L'attribut qu'exigent les actions sensibles du bundle : `PHIL_STEP_UP`, ou `IS_AUTHENTICATED_RECENTLY` de Symfony Security. |
| `step_up.lifetime` | `300` | Secondes pendant lesquelles une preuve accorde `PHIL_STEP_UP`. |
| `sessions.touch_interval` | `300` | Le moins de secondes entre deux écritures du moment où une session a été vue pour la dernière fois. |
| `sessions.idle_timeout` | `7200` | Le nombre de secondes sans requête après lequel une session est fermée : 0 pour aucune borne, sinon au moins 60 et plus que `sessions.touch_interval`. L'actualisation d'un jeton OpenID Connect lié à la session compte comme une requête. |
| `sessions.max_age` | `43200` | Le nombre de secondes après son ouverture au-delà duquel une session est fermée, quelle que soit son activité : 0 pour aucune borne, sinon au moins 60. Un jeton d'actualisation lié à la session expire à ce moment, et une actualisation au-delà ferme la session. |
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
| `oidc.signing_keys.source` | `database` | D'où viennent les clés de signature : `database`, faites, scellées et renouvelées par le bundle ; `file`, les fichiers de `files` ; `service`, le service de `service`. Voir [Clés et leur rotation](oidc-provider.md#clés-et-leur-rotation). |
| `oidc.signing_keys.automatic_rotation` | `true` | Si `phil:identity-provider:rotate-keys` fait tourner les clés de la base de données quand le planificateur l'exécute ; `false` les laisse jusqu'à ce que la commande s'exécute avec `--force`. |
| `oidc.signing_keys.files` | `[]` | Les fichiers de clés de la source `file`, chacun un chemin ou `{ path, kid }` : le premier signe, les autres sont publiés. |
| `oidc.signing_keys.sealed` | `false` | Si les fichiers de clés sont scellés sous `secrets.kms_key`, par `phil:identity-provider:seal-key`. |
| `oidc.signing_keys.service` | `null` | L'id du service qui implémente `SigningKeySource`, pour la source `service`. |
| `oidc.extra_scopes` | `[]` | Les portées, outre `openid`, `profile`, `email`, `phone` et `offline_access`, que les clients peuvent enregistrer, comme les portées d'une ressource. |
| `oidc.client_authentication.methods` | les quatre | Les méthodes par lesquelles les clients peuvent s'authentifier : `client_secret_basic`, `client_secret_post`, `private_key_jwt`, `none`. Voir [Authentification des clients](oidc-provider.md#authentification-des-clients). |
| `oidc.client_authentication.clock_skew` | `60` | Secondes dont une heure d'une assertion de client peut être décalée, de 0 à 300. |
| `oidc.client_authentication.assertion_max_lifetime` | `300` | Le plus loin devant, en secondes, que peut être l'expiration d'une assertion de client, de 1 à 3600 ; son `jti` est gardé aussi longtemps. |
| `oidc.client_authentication.legacy_assertion_audiences` | `false` | Si une assertion de client peut aussi nommer le point de terminaison des jetons à l'origine de l'émetteur, et ses audiences dans un tableau ; désactivé, son `aud` est l'émetteur, sous forme de chaîne (draft-ietf-oauth-rfc7523bis). |
| `oidc.require_nonce_without_pkce` | `true` | Si une demande d'autorisation sans défi PKCE, d'un client enregistré avec `--pkce-optional`, doit porter un `nonce` (RFC 9700, section 2.1.1) ; `false` seulement pour une suite de tests qui joue un tel client sans `nonce`. |
| `oidc.refresh_token_bound_to_session` | `true` | Si un jeton d'actualisation émis dans une session est refusé une fois la session fermée, au-delà de `sessions.idle_timeout` ou de `sessions.max_age` — l'actualisation la ferme alors —, ou plus vieille que `oidc.session_max_age`, sa famille révoquée ; une actualisation compte comme l'activité de la session. |
| `oidc.session_max_age` | `null` | Secondes après l'ouverture d'une session au-delà desquelles ses jetons d'actualisation ne sont plus émis ni acceptés ; null pour aucune borne que la fin de la session. |
| `oidc.id_token.acr` | `null` | L'`acr` de chaque jeton d'identité ; null pour aucun. |
| `oidc.id_token.idp` | `null` | L'`idp` de chaque jeton d'identité ; null pour aucun. |
| `oidc.id_token.session_start` | `false` | Si le jeton d'identité porte `session_start`, l'ouverture de sa session. |
| `oidc.subject.type` | `account` | Le `sub` d'un compte : `account` pour son identifiant interne, `public` pour un identifiant opaque le même pour tous les clients, `pairwise` pour un par secteur. Voir [Jetons](oidc-provider.md#jetons). |
| `oidc.subject.salt` | `null` | Le secret, de 32 octets au moins, sous lequel les sujets opaques et par paire sont dérivés ; exigé par `public` et `pairwise`, et jamais changé une fois que les clients ont vu des sujets. |
| `oidc.backchannel_logout.enabled` | `false` | Si les clients qui ont enregistré une adresse de déconnexion par canal arrière reçoivent un jeton de déconnexion quand prend fin une session dont ils ont des jetons ; exige `symfony/messenger` et `symfony/http-client`. Voir [Déconnexion par canal arrière](oidc-provider.md#déconnexion-par-canal-arrière). |
| `oidc.backchannel_logout.bus` | `messenger.default_bus` | Le bus de messages de `SendLogoutToken`. |
| `oidc.backchannel_logout.http_client` | `http_client` | Le client HTTP qui poste les jetons de déconnexion. |
| `oidc.backchannel_logout.timeout` | `5` | Secondes dont dispose un client pour répondre, de 1 à 30. |

## Un site qui refuse certaines méthodes

Un site qui n'offre ni liens de connexion ni messages texte laisse `login_link` et `sms` désactivés : leurs routes
n'existent pas, leurs contrôleurs ne sont pas enregistrés et aucune page n'y mène.
