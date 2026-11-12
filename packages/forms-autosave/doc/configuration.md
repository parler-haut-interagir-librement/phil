# Configuration

```yaml
# config/packages/phil_forms_autosave.yaml
phil_forms_autosave:
    secret: '%kernel.secret%'   # what keys and owners' hashes are derived with
    ttl: 604800                 # seconds a draft is kept after its last change, in the browser and on the server
    debounce: 800               # milliseconds the controller waits after the last change before it saves
    max_bytes: 262144           # how large a draft the controller keeps in the browser
    server:
        enabled: false          # whether drafts are also kept on the server, for signed-in users
        store: doctrine         # "doctrine", or "memory" for tests
        connection: default     # the Doctrine connection of the table
        encrypt: true           # sealed through Symfony's KeyManagement component; false stores them in clear
        key: ~                  # the master key of the KMS that seals drafts, required while encrypt is true
        kms: ~                  # a client such as key_management.vault; by default, the component's default client
        max_bytes: 65536        # how large a draft the server keeps
        owner: ~                # a DraftOwner service; by default, the user of Symfony Security
```

| Option | Bounds | Effect |
|---|---|---|
| `secret` | not empty | Changing it gives every form a new key: the drafts kept under the old ones are no longer found |
| `ttl` | 60 to 31,536,000 | A draft older than this is removed when read in the browser, and purged on the server |
| `debounce` | 100 to 10,000 | The controller saves once nobody typed for this long, and at once when the form is sent or the page left |
| `max_bytes` | 1,024 to 4,194,304 | A larger draft is not kept in the browser, and the status says so |
| `server.enabled` | | Turns on the store, its endpoints, its purge command and the mapping of its table |
| `server.store` | `doctrine`, `memory` | `memory` keeps drafts in the process, emptied between requests |
| `server.connection` | not empty | The connection `doctrine.dbal.<name>_connection` |
| `server.encrypt` | | With `true`, `symfony/key-management` and a master key are required; `false` keeps what users typed in clear in the table |
| `server.key` | not empty | The master key of the KMS that seals drafts — a key of a `sodium://` DSN, an alias, an ARN |
| `server.kms` | not empty | The service of a client of the KeyManagement component, such as `key_management.<client>`; without it, the default client |
| `server.max_bytes` | 1,024 to 4,194,304 | A larger draft is refused with `413`; a body more than twice as large is not read |
| `server.owner` | | A service implementing `DraftOwner`; without it, the user of Symfony Security when SecurityBundle is installed |

The server store stops the container with a message when the CSRF protection of FrameworkBundle is turned off, when
nothing names the signed-in user, or when encryption is on without the KeyManagement component, without `server.key`,
or without the client that holds it.
