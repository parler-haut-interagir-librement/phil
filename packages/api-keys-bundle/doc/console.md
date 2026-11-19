# Console

The four commands are registered when `symfony/console` is installed.

## phil:api-keys:create

```sh
bin/console phil:api-keys:create alice@example.com "Deploy script" --scope=projects:read --scope=projects:write --expires="+30 days"
```

Creates a token for an owner — the identifier its user is loaded by — and prints, on a second line of its own, its
text: copy it then, it is not shown again. `--owner-type` names another owner type than `user`:

```sh
bin/console phil:api-keys:create acme "Billing export" --owner-type=organization --scope=billing
```
 `--expires` takes a date (`2027-01-31`) or a relative time (`+30 days`),
read from the bundle's clock; without it, the default lifetime. A bad expiry, an undeclared scope, a name out of shape
or an expiry past the longest lifetime, an undeclared owner type or a scope that type may not hold, prints why and
returns `2`.

## phil:api-keys:list

```sh
bin/console phil:api-keys:list --owner=alice@example.com --limit=20
bin/console phil:api-keys:list --owner=acme --owner-type=organization
```

A table of the tokens, the newest first: identifier, owner type, owner, name, scopes, creation, expiry, last use and state —
`active`, `revoked` or `expired`. Never a secret: the store has none.

## phil:api-keys:revoke

```sh
bin/console phil:api-keys:revoke 3kTMd9qXbW1z 8hPq2LmZ0aRt
```

Revokes tokens by identifier, at once. An identifier without a token is reported, the others are still revoked, and
the command returns `1`.

```sh
bin/console phil:api-keys:revoke --owner=acme --owner-type=organization
```

With `--owner`, revokes every active token of that owner instead, and prints how many; identifiers and `--owner`
together, or neither, return `2`.

## phil:api-keys:purge-expired

```sh
bin/console phil:api-keys:purge-expired --retention=30
```

Removes the tokens that expired or were revoked more than the retention ago, in days, `purge_retention` by default.
Run it each day:

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('@daily', new RunCommandMessage('phil:api-keys:purge-expired'));
```
