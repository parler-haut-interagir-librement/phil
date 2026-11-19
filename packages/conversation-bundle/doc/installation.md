# Installation

## Requirements

PHP 8.5 or later with `ext-sodium` and `ext-mbstring`, Symfony 8.2 or later with its KeyManagement component, and
Doctrine DBAL 4 with DoctrineBundle. For the pages: Twig, Symfony UX Twig Component, Routing, Symfony Security's CSRF
protection, and the recipes `Button`, `Badge` and `Textarea` of the kit. Optional: the Doctrine ORM, for the tables
added to the schema it generates; the console, for the rotation command; Symfony Security, for the current participant;
`phil/notifications-bundle`, MercureBundle and `phil/privacy-bundle`.

## Enabling the bundle

```sh
composer require phil/conversation-bundle
```

```php
// config/bundles.php
return [
    // …
    Symfony\Component\KeyManagement\KeyManagementBundle::class => ['all' => true],
    Phil\ConversationBundle\PhilConversationBundle::class => ['all' => true],
];
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_conversation.yaml`,
which names the master key `%env(PHIL_CONVERSATION_KEY)%`, adds `PHIL_CONVERSATION_KEY=conversations` to `.env`, and
imports the pages under `/messages` in `config/routes/phil_conversation.yaml`. The key management service must then
hold a master key of that name, as below. The recipe lives in the recipes repository of PHIL, read once the application
lists that repository in `extra.symfony.endpoint`; without it, register the bundle and write the configuration by hand.

## The key management service

The conversations are never kept in the clear: `phil_conversation.encryption.key` is required, and names a master key
of a client of the KeyManagement component. A local client holds its keys itself, from the environment:

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=%env(CONVERSATIONS_MASTER_KEY)%'
```

```sh
# 32 random bytes in base64url, kept in the secrets of the application, never in the repository
php -r 'echo rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "="), PHP_EOL;'
```

A client of a cloud key management service names its key there instead; see the component's documentation. The bundle
uses the component's default client, or the one `phil_conversation.encryption.kms` names. When that client is not
defined, the container stops and says what to change. See [Encryption at rest](encryption.md).

## The tables

The bundle keeps five tables through DBAL, on the connection `phil_conversation.connection` names. With the Doctrine
ORM, they are added to the schema it generates for that connection, so that the application's migrations create them:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, `ConversationTables::configure()` adds them to a `Schema`, from which a migration or a script writes the
SQL:

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;

foreach (ConversationTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

| Table | Holds |
|---|---|
| `phil_conversation` | A conversation: its UUID, its kind, its sealed subject and the name of its master key, when it started, the moment and sequence of its last message, and, for a direct conversation, the hash of its two participants, unique |
| `phil_conversation_participant` | A participant of a conversation: their identifier, when they joined, the sequence of the last message they read, whether they muted it, when they left it |
| `phil_conversation_message` | A message: its sequence, its UUID, its conversation, its author, its sealed body and the name of its master key, when it was sent |
| `phil_conversation_reception` | Whom a participant lets start a conversation with them, when it is not everyone |
| `phil_conversation_block` | A person a participant blocked |

Moments are kept in UTC. The bundle runs on MySQL 8.4, PostgreSQL 17 and SQLite.

## The routes

```yaml
# config/routes/phil_conversation.yaml
phil_conversation:
    resource: '@PhilConversationBundle/config/routes.php'
    prefix: /messages
```

Put the prefix behind the application's firewall: without a signed-in participant, the pages are denied.

## The recipes of the kit

The pages draw their buttons, counts and text areas with the recipes `Button`, `Badge` and `Textarea` of the kit,
installed by the Symfony UX Toolkit and used as they are:

```sh
php bin/console ux:install button badge textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The bundle reads the components subdirectory of the Toolkit's configuration; `phil_conversation.kit_subdirectory`
names another.

## The assets

With AssetMapper and the Stimulus bundle, the controller that loads the new messages of an open conversation is served
without a build step under `@phil/conversation-bundle`. It is used only when [the real time](integrations.md#real-time)
is enabled.
