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

| Option | Meaning |
|---|---|
| `enabled` | Registers the services; true by default. |
| `default_provider` | The provider a room is scheduled on when none is named; one of `providers`. |
| `providers` | The servers, by a name of 1 to 64 lowercase letters, digits, `_`, `.` or `-`. |
| `store` | `dbal`, the tables of a connection; `memory`, for tests; or the identifier of a `ClassroomStore` service. |
| `connection` | The Doctrine DBAL connection of the tables. |
| `table_prefix` | The start of the names of the tables, 1 to 41 lowercase letters, digits or `_`. |
| `clock` | The PSR-20 clock that dates the rooms and the stays. |
| `http_client` | The HTTP client the BigBlueButton servers are called with. |
| `webhook_secret` | The secret of the proof of the webhook addresses. Changing it changes every address. |
| `allow_localhost` | Whether a callback address on `localhost` is written. |
| `moderator_role` | The role that makes the user a moderator; the room is the subject of the check, so a voter may decide per room. |
| `current_participant` | A service replacing the authenticated user of Symfony Security. |
| `courses.enabled`, `courses.threshold` | The activity type of phil/courses-bundle, and its default threshold, 1 to 100. |
| `privacy.enabled` | The stays in the access and erasure requests of phil/privacy-bundle. |

## The providers

| Type | Required | Optional |
|---|---|---|
| `bigbluebutton` | `url`, `secret` | `checksum`, `analytics`, `signed_analytics`, `timeout`, `max_duration`, `max_response_bytes` |
| `jitsi` | `url`, `app_id`, `secret` | `webhook_secret`, `token_ttl`, `audience`, `subject` |
| `jaas` | `app_id`, `key_id`, `private_key` | `webhook_secret`, `token_ttl`, `url` |
| `service` | `service` | — |

Each provider is a service `phil_virtual_classroom.provider.<name>`, built when first used. What each adapter does,
and its limits, is in the documentation of phil/virtual-classroom.
