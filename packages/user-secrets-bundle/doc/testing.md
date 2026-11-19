# Testing

## The bundle's tests

The suite `tests/Unit` tests the value types and credentials; the row; the header of format and algorithm, the
KeyManagement cipher over the component's local KMS and the envelope — sealing, opening by the header, values of the
earlier format, a header changed, altered and malformed values, values of another master key or another row, a KMS that
fails —; `UserSecrets` on a store that keeps copies, as a database does — storing, listing, reading by the owner's task,
refreshing, revoking, removing, erasing, the events —; the revocation at the service — after the local change, a
service that fails, a value that does not open —; the races of a refresh between two processes — with and without the
lock, a row re-keyed, revoked or removed meanwhile, a row that keeps changing —; the refresh lock over Symfony's Lock
component; the key rotation — to a new master key, out of the earlier format, a value that does not open named while the
others rotate, a row stored, refreshed or revoked during a batch and left as the other process made it, the count of
what is left, between master keys of a KMS —; one contract for both stores, compare-and-swap included, run in memory and on Doctrine with
SQLite in memory; the privacy module, the logger auditor and the commands; the generated master key read by a
`sodium://` DSN; and the configuration, the wiring, the check of the KMS and of the lock factory. The suite
`tests/Functional` boots a small application with FrameworkBundle and its lock factory, DoctrineBundle on SQLite, the
privacy bundle and KeyManagementBundle with a local `sodium://` client, its master key read from an environment
variable, and follows a person's secrets from storage to erasure, a token refreshed under the lock and revoked at its
service.

The suite `tests/Integration` runs the contract of the stores on MySQL 8 and PostgreSQL 17 — the order of the key
rotation, the compare-and-swap on a `TEXT` column —, and is skipped without their URLs.

From the monorepo root:

```sh
vendor/bin/phpunit packages/user-secrets-bundle/tests
vendor/bin/phil-dev services up && vendor/bin/phil-dev services test -- packages/user-secrets-bundle/tests/Integration
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses it

Keep secrets in memory and give the test environment a local client and a master key of its own:

```yaml
# config/packages/test/key_management.yaml
key_management:
    clients:
        test: 'sodium://?keys[test]=dGVzdC1rZXktdGVzdC1rZXktdGVzdC1rZXktdGVzdCE'

# config/packages/test/phil_user_secrets.yaml
phil_user_secrets:
    store: memory
    current_key: test
```

```php
$secrets = self::getContainer()->get(UserSecrets::class);
$secrets->store(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-test'));
```

Replace a `TokenRefresher` by a service that returns a fixed token, a `TokenRevoker` by one that keeps the tokens it
is given, and register a `SecretAuditor` that keeps the events to assert what was read and why.
