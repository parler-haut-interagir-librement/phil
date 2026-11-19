# Testing

## The bundle's tests

The suite `tests/Unit` tests the identifiers and the models; `Conversations` and `Reception` over the DBAL stores on
SQLite — direct and group conversations, the reception settings and blocks, the participant check, unread, muting,
leaving, the inbox and the thread by page, the events, two first messages at once —; the sealer, its format and every altered text it refuses; the
stores, their moments in UTC, the unique direct conversation, the erasure and the schema listener; the rotation and its
command; the notifications, the subject of a notification, the Mercure updates and a hub that cannot be reached, the
export and the erasure of phil/privacy-bundle, the current participant of Symfony Security, the Twig functions and the
attributes of the real time; and every definition the configuration wires.

The suite `tests/Functional` boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, the KeyManagement component
with a local key, TwigBundle and the recipes of the kit, and serves the pages: a member writes to another, who reads and
replies; a refused message said on the page; the conversations of others not found; the forms without a token refused;
muting, the blocks and leaving; the pages of a long conversation and of a full inbox.

The suite `tests/Integration` runs the stores on MySQL 8.4 and PostgreSQL 17, at `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL`, and is skipped without them: the conversations kept and read by page, the unique direct
conversation and two first messages at once in an open transaction, the erasure, and a rotation of the master key.

The tests of the Stimulus controller, in `tests/js`, run under Node's own test runner, Stimulus resolved to a stub by
`register.mjs`. `tests/Functional/JavaScriptTest.php` runs them with the other suites; on a machine without Node it is
skipped and says so.

From the monorepo root:

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/conversation-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses conversations

In a kernel test, start the conversations through the service and read the pages as a participant:

```php
$conversations = self::getContainer()->get(Conversations::class);
$conversation = $conversations->startDirect(ParticipantId::fromString('member-7'), ParticipantId::fromString('member-42'), 'Hello');

$client->loginUser($bob);
$client->request('GET', '/messages/'.$conversation->id);
```

A test environment seals with a local client and a fixed key of 32 bytes, never a key of production:

```yaml
# config/packages/test/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s'
```
