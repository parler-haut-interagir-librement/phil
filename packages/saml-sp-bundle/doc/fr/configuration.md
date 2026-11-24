<!-- phil-dev source-sha256=4f17d2022d6094549551271c63a64dd03a31f5b80a40716eb8bb6deedb0de242 -->
# Configuration

```yaml
phil_saml_sp:
    enabled: true
    entity_id: ~                       # the address of the metadata of each identity provider by default
    signing:                           # required unless sign_requests is false
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
        passphrase: ~
    encryption: []                     # key pairs that decrypt assertions, the current one first
    name_id_format: ~                  # e.g. urn:oasis:names:tc:SAML:2.0:nameid-format:persistent
    sign_requests: true
    clock_skew: 120                    # seconds, 0 to 600
    max_age: 600                       # seconds a response may be old, 30 to 86400
    allow_sha1: false
    min_rsa_bits: 2048
    allow_localhost: false             # development only
    bind_to_browser: true
    secure_cookie: true
    default_target_path: /
    logout_target_path: /
    cache_pool: cache.app
    replay_guard:
        store: auto                    # auto, doctrine or cache
        connection: default            # the Doctrine DBAL connection
        table: phil_saml_used_assertion
    single_server: false               # true accepts a cache pool local to the server in prod
    metadata_lifetime: 86400           # seconds, 60 to 2592000
    request_lifetime: 600              # seconds, 60 to 3600
    clock: clock
    http_client: ~
    accounts: ~                        # a SamlAccounts service
    identity_providers:
        acme:
            metadata_url: ~            # one of metadata_url, metadata_file, metadata_xml
            metadata_file: ~
            metadata_xml: ~
            metadata_certificates: []  # PEM certificates the metadata must be signed with
            entity_id: ~
            preset: none               # none, entra_id, okta, generic
            attributes: {}             # field: [attribute names], over the preset
            group_roles: {}            # group or role sent: role of the application
            default_roles: [ROLE_USER]
            want_assertions_signed: true
            want_assertions_encrypted: false
            allow_unsolicited: false
            binding: redirect          # redirect or post
            single_logout: true
            create_accounts: true
            force_authn: false
```

## Le fournisseur de service

- `entity_id` : l'identifiant d'entité sous lequel les fournisseurs d'identité connaissent l'application. Par défaut,
  chaque fournisseur d'identité reçoit l'adresse de sa route de métadonnées, `https://app.example.org/saml/acme/metadata`,
  de sorte que chaque organisation enregistre sa propre application ; donnez une seule valeur pour la partager.
- `signing` : la paire de clés des signatures, en PEM. `%env(file:resolve:…)%` la lit dans le fichier que nomme une
  variable d'environnement. Une clé avec phrase secrète la donne dans `passphrase`.
- `encryption` : les paires de clés qui déchiffrent les assertions chiffrées ; leurs certificats sont publiés dans les
  métadonnées. La paire de signature peut y être donnée de nouveau.
- `name_id_format` : le format que demandent les requêtes. Microsoft Entra ID et Okta envoient leur propre valeur par
  défaut quand aucun n'est demandé.
- `sign_requests` : si les requêtes sont signées. Les messages de déconnexion le sont toujours.

### Renouvellement des clés

1. Faites la paire de clés suivante, et ajoutez-la à `encryption` après la courante ; les métadonnées publient alors les
   deux.
2. Demandez à chaque fournisseur d'identité d'importer de nouveau les métadonnées.
3. Placez la paire suivante dans `signing` et en premier dans `encryption` ; retirez l'ancienne quand aucune assertion
   chiffrée pour elle ne peut plus arriver.

## Options de sécurité

- `clock_skew`, `max_age` : voir [Sécurité](security.md#temps).
- `allow_sha1` : accepte les signatures avec SHA-1, pour un fournisseur d'identité qui ne peut faire mieux. Refusé par
  défaut.
- `min_rsa_bits` : la plus petite clé RSA dont une signature peut provenir.
- `allow_localhost` : accepte les adresses sur `localhost`, et les services en HTTP simple dans les métadonnées, pour un
  fournisseur d'identité exécuté sur la machine du développeur.
- `bind_to_browser`, `secure_cookie` : voir [Sécurité](security.md#le-navigateur).

## Où vont les gens

- `default_target_path` : après une connexion qui n'a nommé aucune `target`.
- `logout_target_path` : après que le fournisseur d'identité confirme une déconnexion.

## Services

- `cache_pool` : la réserve PSR-6 des requêtes en attente, des métadonnées récupérées et, sans base de données, des
  assertions acceptées. Elle est partagée par chaque serveur et n'est pas vidée au déploiement ; une réserve à part, sur
  Redis ou une base de données, est le choix sûr.
- `replay_guard` : où les assertions acceptées sont retenues, pour qu'une assertion postée de nouveau soit refusée.
  - `store` : `auto`, la valeur par défaut, prend la table de `DbalReplayGuard` sur la connexion Doctrine DBAL quand
    l'application en a une — le service `doctrine.dbal.<connection>_connection` que définit DoctrineBundle — et
    `CacheReplayGuard` sur `cache_pool` sinon ; `doctrine` exige la connexion, et le conteneur ne se construit pas sans
    elle ; `cache` prend la réserve de cache même avec une connexion.
  - `connection` : le nom de la connexion Doctrine DBAL, `default` par défaut.
  - `table` : la table des assertions acceptées, d'au plus 63 lettres minuscules, chiffres ou `_` ; une migration de
    l'application la crée, voir [Installation](installation.md#la-table-des-assertions-acceptées).
- `single_server` : dans l'environnement `prod`, le conteneur ne se construit pas quand les assertions acceptées sont
  gardées dans une réserve de cache locale à chaque serveur — l'adaptateur APCu, tableau, système de fichiers ou
  système, comme l'est `cache.app` par défaut — ; mettez-le à `true` quand l'application tourne sur un seul serveur.
  Voir [Sécurité](security.md#rejeu).
- `metadata_lifetime`, `request_lifetime` : combien de temps les métadonnées récupérées et une requête en attente sont
  gardées.
- `clock` : l'horloge PSR-20.
- `http_client` : le client avec lequel les métadonnées sont récupérées, enveloppé par phil/safe-http.
- `accounts` : le service qui implémente `SamlAccounts`.

## Fournisseurs d'identité

La clé — de 1 à 64 lettres minuscules, chiffres, `_` ou `-` — fait partie des routes du fournisseur d'identité.

- `metadata_url`, `metadata_file`, `metadata_xml` : d'où viennent les métadonnées, exactement une source. Une adresse est
  en HTTPS, récupérée par phil/safe-http et gardée `metadata_lifetime` secondes ; un fichier est lu à chaque requête.
- `metadata_certificates` : quand il est défini, les métadonnées doivent être signées avec l'un d'eux.
- `entity_id` : l'identifiant d'entité à lire dans des métadonnées qui en décrivent plusieurs, et contrôlé autrement.
- `preset` : les noms de revendications de [Microsoft Entra ID](entra-id.md#revendications),
  d'[Okta](okta.md#déclarations-dattributs) ou des schémas LDAP et eduPerson.
- `attributes` : pour chacun des champs `identifier`, `email`, `given_name`, `family_name`, `display_name`, `groups`,
  `roles`, les noms d'attributs à lire, dans l'ordre ; ils remplacent les noms du préréglage pour ce champ.
- `group_roles`, `default_roles` : les rôles d'un `SamlUser` (voir [Comptes et rôles](accounts.md#rôles)).
- `want_assertions_signed` : à false, accepte une réponse signée qui entoure une assertion non signée.
- `want_assertions_encrypted` : à true, refuse une assertion qui n'est pas chiffrée.
- `allow_unsolicited` : accepte une réponse que le fournisseur d'identité envoie sans qu'on la demande, quand une
  personne ouvre l'application depuis son portail ; voir [Sécurité](security.md#réponses-non-sollicitées).
- `binding` : la liaison des requêtes ; HTTP-Redirect, ou HTTP-POST pour un fournisseur d'identité qui l'exige.
- `single_logout` : si la déconnexion est transmise vers ce fournisseur d'identité et depuis lui.
- `create_accounts` : si `SamlAccounts::create()` est appelé à une première connexion.
- `force_authn` : demande à la personne de s'authentifier de nouveau à chaque connexion.
