# phil/conversation-bundle

**Private messages for Symfony: conversations between two members or in groups, sealed at rest.**

phil/conversation-bundle gives an application private messages. A member writes to another, and the two share one
direct conversation; or a member starts a group, with a subject, and brings others in. Participants are named by the
stable identifier the application gives its members — never an email address, which the bundle refuses. Every subject
and every message is sealed at rest by Symfony's KeyManagement component: a fresh data key per text, wrapped by a
master key of the application's key management service, bound to its row, in a format that names its version and its
algorithm. A rotation command seals the old texts again under a new master key.

Each member decides what they receive: whether everyone, their contacts or nobody may start a conversation with them,
and whom they block. Each conversation keeps what each participant read, the inbox counts what is unread, and a
participant may mute a conversation or leave it. The pages — the inbox, a conversation and its reply form, the form that
writes to a member, the reception settings — work without JavaScript and draw their buttons, badges and text areas
with the recipes of the kit, installed by the Symfony UX Toolkit.

It is for a site whose members talk to each other: a club, a community, a marketplace between buyers and sellers. It is
also the ground other packages build on: a help desk whose requests are conversations, a live chat with visitors.

The bundle keeps its tables through DBAL, without the ORM, so that a long conversation and a full inbox are read by
page. phil/notifications-bundle, Mercure and phil/privacy-bundle are optional: with the first, the recipients of a
message are notified, those who muted the conversation or blocked the author excepted; with Mercure, the open pages
load the new messages as they come; with the last, a member's conversations take part in the export and the erasure of
their data. The services keep no state between requests and are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Direct and group conversations, participants named by stable identifiers, an `@` refused | Implemented and tested |
| Subjects and bodies sealed at rest by the KeyManagement component, `pcv1.a256gcm.`, bound to their row | Implemented and tested |
| The rotation of the master key and `phil:conversation:rotate-keys` | Implemented and tested |
| Reception settings: everyone, contacts or nobody, and blocks | Implemented and tested |
| Read and unread, muting, leaving, inbox and thread read by page | Implemented and tested |
| DBAL stores, the schema by `Schema::editor()`, moments in UTC; MySQL 8.4, PostgreSQL 17 and SQLite | Implemented and tested |
| The pages: inbox, conversation, writing to a member, reception settings, with the recipes of the kit, English and French | Implemented and tested |
| The notification of each message through `phil/notifications-bundle` | Implemented and tested |
| The Mercure update of each message and its Stimulus controller, a hub that cannot be reached caught | Implemented and tested |
| The export and the erasure of a member's conversations through `phil/privacy-bundle` | Implemented and tested |
| `ConversationReader`, `startOpen()` and `join()`, for the packages built on the bundle | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the key management service, the tables, the routes, the recipes of the kit, the recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Conversations](doc/conversations.md) | Starting, writing, reading, unread, muting, leaving, the reception settings and the pages. |
| [Encryption at rest](doc/encryption.md) | The sealed format, its context, the master keys and their rotation. |
| [Notifications, real time and privacy](doc/integrations.md) | What the bundle does with phil/notifications-bundle, Mercure and phil/privacy-bundle. |
| [Building on the bundle](doc/building-on.md) | `ConversationReader`, open conversations and `join()`, for a help desk or a live chat. |
| [Extension points](doc/extension-points.md) | The ports, the events, the services to replace, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and testing code that uses it. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/conversation-bundle
php bin/console ux:install button badge textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=%env(CONVERSATIONS_MASTER_KEY)%'

# config/packages/phil_conversation.yaml
phil_conversation:
    encryption:
        key: conversations

# config/routes/phil_conversation.yaml
phil_conversation:
    resource: '@PhilConversationBundle/config/routes.php'
    prefix: /messages
```

```php
$conversation = $conversations->startDirect(ParticipantId::fromString($me), ParticipantId::fromString($seller), 'Is the lamp still for sale?');
```

## Requirements

PHP 8.5 or later with `ext-sodium`, Symfony 8.2 or later with its KeyManagement component, and Doctrine DBAL 4.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
