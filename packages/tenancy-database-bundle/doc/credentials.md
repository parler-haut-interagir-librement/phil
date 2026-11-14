# Credentials

A dedicated database is reached, by default, on the server of the shared connection, with its account, under its own
name. A database on another server, or behind an account of its own, gets its credentials from the application through
`TenantCredentials`:

```php
use Phil\TenancyDatabaseBundle\Application\Contract\TenantCredentials;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use Phil\TenancyDatabaseBundle\Infrastructure\Credentials\CredentialSeal;

final readonly class StoredCredentials implements TenantCredentials
{
    public function __construct(
        private Connection $platform,      // a connection that does not follow the tenant
        private CredentialSeal $seal,
    ) {
    }

    public function credentialsFor(string $database): ?DatabaseCredentials
    {
        $sealed = $this->platform->fetchOne('SELECT credentials FROM tenant_database WHERE name = ?', [$database]);

        return \is_string($sealed) ? $this->seal->open($database, $sealed) : null;
    }
}
```

```yaml
phil_tenancy_database:
    credentials: App\Tenancy\StoredCredentials
```

`credentialsFor()` is asked each time the connection opens on that database; return `null` to keep the account of the
shared connection.

## `DatabaseCredentials`

A user, a password, and optionally a host and a port. The constructor refuses an empty user, an empty host, a control
character in any value and a port outside 1 to 65535. The password is a sensitive parameter, absent from stack traces;
a dump shows it as `***`; serializing the object throws, so that it never reaches a cache entry or a Messenger message.

## The sealed format

Credentials stored in a database are sealed with `CredentialSeal`, under a 32-byte key kept out of that database:

```text
ptdb.v1.xchacha20poly1305.<base64url(nonce ‖ ciphertext)>
```

- **Named and versioned**: the prefix gives the format, its version and its algorithm, XChaCha20-Poly1305 of
  libsodium. A value of another format, version or algorithm is refused, never read as this one.
- **Bound to its database**: the database name is authenticated with the value, so credentials sealed for one database
  do not open for another, even under the same key.
- **Bounded**: a sealed value longer than 4 KiB is refused before it is decoded.

```php
$seal = new CredentialSeal(sodium_base642bin($_ENV['TENANT_CREDENTIALS_KEY'], SODIUM_BASE64_VARIANT_ORIGINAL));
$sealed = $seal->seal('tenant_retail', new DatabaseCredentials('retail', $password, 'db2.internal'));
```

## What is never written out

- The log lines of the bundle name a tenant's reference and a database name, never a host, a user or a password.
- A Messenger message carries the tenant's reference only.
- What a migration process prints is shown on the operator's console, never logged.
- A visitor answered with a `503` reads that the site is under maintenance, nothing more.
