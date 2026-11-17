<!-- phil-dev source-sha256=c00094a0e72f72c59eb1b05373d93d8bcd7b73ec063b328f0e7d9cfe9f4815b2 -->
# Configuration

```yaml
# config/packages/phil_lti.yaml
phil_lti:
    store: dbal                      # dbal | memory
    connection: default
    table_prefix: phil_lti_
    clock: clock
    cache: cache.app
    http_client: ~                   # under the phil/safe-http clients; a client of its own when null
    message_bus: ~                   # scores sent at once when null
    allow_localhost: false
    kit_subdirectory: ~

    keys:
        source: dbal                 # dbal | config
        kms: Symfony\Component\KeyManagement\DataKeyGeneratorInterface
        master_key: lti
        lifetime: 7776000            # 90 days
        overlap: 604800              # 7 days
        bits: 2048
        private_key: ~               # source config
        passphrase: ~
        key_id: ~
        retired_public_keys: []

    tool:
        enabled: true
        state_lifetime: 600
        refused_score_retention: 2592000
        platforms:
            - issuer: 'https://lms.example.org'
              client_id: '%env(LTI_CLIENT_ID)%'
              authorization_url: 'https://lms.example.org/lti/authorize'
              token_url: 'https://lms.example.org/lti/token'
              key_set_url: 'https://lms.example.org/lti/jwks'
              token_audience: ~
              deployments: ['1']

    platform:
        enabled: true
        issuer: 'https://courses.example.com'
        token_url: 'https://courses.example.com/lti/platform/token'
        token_lifetime: 3600
        deep_linking_retention: 86400
        tools:
            - client_id: 'client-42'
              name: 'The quiz tool'
              login_url: 'https://tool.example.com/lti/login'
              redirect_urls: ['https://tool.example.com/lti/launch']
              key_set_url: 'https://tool.example.com/lti/jwks'
              deep_linking_url: ~
              scopes:
                  - 'https://purl.imsglobal.org/spec/lti-ags/scope/score'
                  - 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly'
              deployments: ['1']
```

## Clés communes

| Clé | Valeur par défaut | Ce que c'est |
|---|---|---|
| `store` | `dbal` | `dbal` : les tables d'une connexion DBAL ; `memory` : un seul processus, pour les tests. |
| `connection` | `default` | La connexion DBAL de DoctrineBundle où se trouvent les tables. |
| `table_prefix` | `phil_lti_` | Le préfixe des huit tables : de 1 à 40 lettres minuscules, chiffres ou `_`. |
| `clock` | `clock` | Le service de l'horloge PSR-20. |
| `cache` | `cache.app` | Le service du cache des jeux de clés et des jetons d'accès. |
| `http_client` | null | Le service du client HTTP de Symfony sous les deux clients de `phil/safe-http` du bundle — `phil_lti.service_http_client` pour le point de terminaison des jetons et les services, `phil_lti.key_set_http_client` pour les jeux de clés — qui refusent les réseaux privés, gardent les en-têtes sur l'origine de l'adresse et bornent le temps et les octets d'une réponse. |
| `message_bus` | null | Le service du bus Messenger qui envoie les notes de l'outil. |
| `allow_localhost` | `false` | Si `localhost` et les noms sous `.localhost` sont des adresses : en développement seulement. |
| `kit_subdirectory` | null | Le sous-répertoire des composants des recettes du kit ; celui du Toolkit lorsqu'il est null. |

## Clés

| Clé | Valeur par défaut | Ce que c'est |
|---|---|---|
| `keys.source` | `dbal` | `dbal` : clés fabriquées, renouvelées et scellées dans la table ; `config` : une clé privée de l'environnement. |
| `keys.kms` | `DataKeyGeneratorInterface` | Le KMS du KeyManagement de Symfony qui scelle les clés de la table. |
| `keys.master_key` | null | La clé maîtresse du KMS ; exigée avec des clés `dbal` dans un magasin `dbal`. |
| `keys.lifetime` | 90 jours | Combien de temps une clé signe avant que `phil:lti:rotate-keys --if-due` la remplace. |
| `keys.overlap` | 7 jours | Combien de temps une clé retirée reste publiée. |
| `keys.bits` | `2048` | Les bits d'une nouvelle clé, de 2048 à 4096. |
| `keys.private_key` | null | La clé privée en PEM ; exigée avec des clés `config`. |
| `keys.passphrase` | null | Sa phrase secrète. |
| `keys.key_id` | null | Son identifiant dans le jeu de clés ; son empreinte lorsqu'il est null. |
| `keys.retired_public_keys` | `[]` | Les clés publiques en PEM des rotations précédentes, encore publiées. |

Avec le magasin `memory`, les clés viennent de la configuration, quelle que soit la source.

## L'outil

| Clé | Valeur par défaut | Ce que c'est |
|---|---|---|
| `tool.enabled` | `false` | Si l'application est un outil. |
| `tool.state_lifetime` | `600` | Les secondes entre l'initiation de connexion et le lancement, de 60 à 3 600. |
| `tool.refused_score_retention` | `2592000` | Les secondes pendant lesquelles une note que la plateforme a refusée pour de bon est gardée, comme trace, avant que `phil:lti:purge` la retire ; un jour au moins. |
| `tool.platforms` | `[]` | Les plateformes auprès desquelles l'outil est enregistré : émetteur, client, adresses d'autorisation, des jetons et du jeu de clés, audience des jetons, déploiements. |

## La plateforme

| Clé | Valeur par défaut | Ce que c'est |
|---|---|---|
| `platform.enabled` | `false` | Si l'application est une plateforme. |
| `platform.issuer` | null | L'émetteur par lequel les outils enregistrent la plateforme ; exigé. |
| `platform.token_url` | null | L'adresse du point de terminaison des jetons, telle que les outils l'enregistrent : l'audience de leurs assertions ; exigée. |
| `platform.token_lifetime` | `3600` | Les secondes de vie d'un jeton d'accès, de 60 à 86 400. |
| `platform.deep_linking_retention` | `86400` | Les secondes pendant lesquelles une réponse de Deep Linking vérifiée est gardée, pour être traitée de nouveau quand son traitement a échoué, de 600 à 2 592 000. |
| `platform.tools` | `[]` | Les outils enregistrés : client, nom, adresse de connexion, adresses de redirection, jeu de clés, adresse de Deep Linking, portées de LTI Advantage, déploiements. |

Le côté plateforme a besoin des `LaunchMessages` de l'application ; le conteneur s'arrête avec un message qui le dit
lorsqu'ils manquent.
