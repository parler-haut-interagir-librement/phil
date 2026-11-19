<!-- phil-dev source-sha256=5a97264ef51add2ec57709a136815be817b98115ecf8d24f2c0b9e16836402cd -->
# Configuration

```yaml
phil_virtual_classroom:
    default_provider: main               # the first provider by default
    providers:
        main:
            type: bigbluebutton
            url: '%env(BBB_URL)%'        # https://bbb.example.com/bigbluebutton/api/
            secret: '%env(BBB_SECRET)%'
            checksum: sha256             # sha256, sha384 or sha512
            analytics: true              # reads the attendance posted at the end of a session
            signed_analytics: true       # only with its bearer token
            timeout: 5                   # seconds without a byte
            max_duration: 10             # seconds in all
            max_response_bytes: 1048576
        own-jitsi:
            type: jitsi
            url: 'https://meet.example.com'
            app_id: 'my-app'
            secret: '%env(JITSI_SECRET)%'
            webhook_secret: '%env(JITSI_WEBHOOK_SECRET)%'   # none read without it
            token_ttl: 10800
            audience: jitsi
            subject: ~                   # the host of the server by default
        hosted:
            type: jaas
            app_id: 'vpaas-magic-cookie-…'
            key_id: 'vpaas-magic-cookie-…/abc123'
            private_key: '%env(JAAS_PRIVATE_KEY)%'
            webhook_secret: '%env(JAAS_WEBHOOK_SECRET)%'
        mine:
            type: service
            service: App\Classroom\MyProvider   # a VirtualClassroom service
    store: dbal                          # dbal, memory, or a ClassroomStore service
    connection: default
    table_prefix: phil_virtual_classroom
    clock: clock
    http_client: http_client
    webhook_secret: '%kernel.secret%'    # signs the addresses of the webhook, 16 bytes or more
    allow_localhost: false               # callbacks on localhost, in development
    moderator_role: ROLE_VIRTUAL_CLASSROOM_MODERATOR
    current_participant: ~               # a CurrentParticipant service
    courses:
        enabled: true
        threshold: 75                    # the share of a session that completes an activity
    privacy:
        enabled: true
```

| Option | Sens |
|---|---|
| `enabled` | Enregistre les services ; vrai par défaut. |
| `default_provider` | Le fournisseur sur lequel une salle est planifiée quand aucun n'est nommé ; l'un de `providers`. |
| `providers` | Les serveurs, par un nom de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`. |
| `store` | `dbal`, les tables d'une connexion ; `memory`, pour les tests ; ou l'identifiant d'un service `ClassroomStore`. |
| `connection` | La connexion Doctrine DBAL des tables. |
| `table_prefix` | Le début des noms des tables, de 1 à 41 lettres minuscules, chiffres ou `_`. |
| `clock` | L'horloge PSR-20 qui date les salles et les séjours. |
| `http_client` | Le client HTTP avec lequel les serveurs BigBlueButton sont appelés. |
| `webhook_secret` | Le secret de la preuve des adresses du webhook. Le changer change chaque adresse. |
| `allow_localhost` | Si une adresse de rappel sur `localhost` est écrite. |
| `moderator_role` | Le rôle qui fait de l'utilisateur un modérateur ; la salle est le sujet de la vérification, de sorte qu'un *voter* peut décider salle par salle. |
| `current_participant` | Un service qui remplace l'utilisateur authentifié de Symfony Security. |
| `courses.enabled`, `courses.threshold` | Le type d'activité de phil/courses-bundle, et son seuil par défaut, de 1 à 100. |
| `privacy.enabled` | Les séjours dans les demandes d'accès et d'effacement de phil/privacy-bundle. |

## Les fournisseurs

| Type | Obligatoire | Facultatif |
|---|---|---|
| `bigbluebutton` | `url`, `secret` | `checksum`, `analytics`, `signed_analytics`, `timeout`, `max_duration`, `max_response_bytes` |
| `jitsi` | `url`, `app_id`, `secret` | `webhook_secret`, `token_ttl`, `audience`, `subject` |
| `jaas` | `app_id`, `key_id`, `private_key` | `webhook_secret`, `token_ttl`, `url` |
| `service` | `service` | — |

Chaque fournisseur est un service `phil_virtual_classroom.provider.<name>`, construit à sa première utilisation. Ce que
fait chaque adaptateur, et ses limites, se trouve dans la documentation de phil/virtual-classroom.
