# Encryption and key rotation

## What is sealed

What is sealed is a JSON document: the owner, the name, the service and the kind of the row, and the credential. When
it opens, the four are compared with the row. Encryption authenticates the ciphertext, not where it lies: without this
binding, whoever can write to the table could copy one person's ciphertext into another person's row, and the second
person's tasks would use the first person's key. With it, the copy fails with `UnreadableSecret`.

The rest of the row — the service, the kind, the dates, the expiry — is in clear: it is what the owner sees in the
list of their secrets, and what an administrator needs to answer a question without decrypting anything.

## The sealed format

The column `ciphertext` holds bytes in standard base64. Their first byte is the version of the format, which tells a
reader how to open them; the column `format` repeats it when it names a known format, so the key rotation finds in SQL
the values of another format. The column `key_id` names the master key that sealed the value.

| First byte | Format | Layout after the byte | Written |
|---|---|---|---|
| `3` | `SealedFormat::Algorithm` | the byte of the algorithm, then what the algorithm wrote | every new value |
| `2` | `SealedFormat::KeyManagement` | a self-contained envelope of Symfony's KeyManagement component, AES-256-GCM implied | never; read |

In the current format the second byte names the algorithm that sealed the value:

| Second byte | Algorithm | What follows |
|---|---|---|
| `1` | `SealedAlgorithm::KeyManagementAes256Gcm` | a self-contained envelope of Symfony's KeyManagement component: the data key wrapped by the KMS, the value encrypted with AES-256-GCM |

Both bytes of the header are part of the additional authenticated data: a header changed to the earlier format or to
another algorithm does not open. A value whose first byte names no known format, or whose second byte names no known
algorithm, fails with `UnreadableSecret`. Values of the earlier format open as they are, and the key rotation seals
them again in the current one.

## Envelope encryption through KeyManagement

Sealing and opening go through `Application\Contract\EnvelopeCipher`, whose one implementation,
`Infrastructure\KeyManagement\KeyManagementCipher`, is the only class that calls Symfony's KeyManagement component.
The component is experimental in Symfony 8.2: its API may change in a minor version, outside Symfony's backward
compatibility promise. Its changes stay in that adapter, and the format's header names the algorithm, so a later
cipher can sit beside it.

Every new value is sealed by `Symfony\Component\KeyManagement\EnvelopeEncrypter`: a fresh 32-byte data key per value,
wrapped by the KMS under the master key `current_key` names; the value encrypted locally with AES-256-GCM under the
data key; the envelope keeps the master key's identifier, the wrapped data key, the nonce, the tag and the ciphertext.
The master key never leaves the KMS; only the wrapped data key goes to it.

The header and the row — the format's byte, the algorithm's byte, then owner, name, service and kind, as a JSON list —
are the additional authenticated data of the envelope: a value copied to another row does not open, before its content
is even read. In the earlier format, the row alone was.

`key_id` keeps the identifier of the master key as the configuration writes it — not an identifier the KMS may return
for it, such as a resolved alias —, the one the key rotation compares with `current_key`.

## The KMS

The bundle seals through a client of the component: the default client of KeyManagementBundle — the alias
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface`, the only client or the one `key_management.default_client`
names —, or the service the option `kms` names, any `DataKeyGeneratorInterface` such as `key_management.<client>`. A
client is declared under `key_management.clients` by a DSN: AWS KMS, Azure Key Vault, Google Cloud KMS, HashiCorp Vault
Transit, Scaleway, KMIP through their bridges, or a local KMS. `current_key` is a master key of that client. Without a
client, or with a `kms` that names no service, the container stops with a message that says what to change.

### A local KMS: `sodium://`

With no KMS of its own, an application declares the component's local libsodium KMS, which wraps each data key with
XChaCha20-Poly1305 under a master key of its DSN:

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'
```

Each master key is 32 random bytes in base64url, as `bin/console phil:user-secrets:generate-key` prints them, and is
best read from an environment variable or the Symfony secrets vault. Several keys go in one DSN —
`sodium://?keys[k2026]=…&keys[k2027]=…` —, and `sodium+dir:///etc/keys` reads each master key from a file of that
directory instead. The keys are read when the client is first used, environment variables resolved.

A KMS that cannot be reached or refuses fails, while opening, with `UnreadableSecret` and, while sealing, with
`SealingFailed`; the exception of the component or of its client is the previous one, and its message is repeated, so
an outage still reads as one. A value whose master key the KMS no longer holds fails with `UnreadableSecret` too, as
the KMS does not tell an unknown key from an altered value; sealing under a master key the KMS does not hold fails
with `UnknownKey`.

## Rotating keys

The key rotation seals again under the current master key, in the current format, every value another master key
sealed or that rests in an earlier format.

1. Make a new master key: generate one with `bin/console phil:user-secrets:generate-key` and add it to the `sodium://`
   DSN beside the old one — or create a master key in the application's KMS.
2. Make it `current_key`. New values are sealed under it at once; old ones still open under the old key, which the
   KMS keeps.
3. Seal every value again:

   ```sh
   bin/console phil:user-secrets:rotate --batch=500
   ```

   The command reads the secrets batch by batch, in one pass by owner and name, and writes each value again by a
   compare-and-swap on the sealed value it read, as the refresh of a token does: a secret another process stored,
   refreshed or revoked between the read and the write is left as that process made it, and counted apart. Running
   it again re-seals nothing more. Values of the earlier format are sealed again in the current one on the way, under
   the same key or not. Revoked secrets have no value and are skipped. Each re-sealed secret is a `rekeyed` event for
   the auditors.

   A value that does not open — its master key gone from the KMS, copied from another row, of no known format — is
   left as it is and named by owner and name; the rotation goes on with the others, and the command ends in failure.
   Such a secret can only be stored again by its owner, or revoked.
4. Count what is left:

   ```sh
   bin/console phil:user-secrets:rotate --dry-run
   ```

   The dry run writes nothing: it counts the secrets not yet sealed under the current key, or not yet in the current
   format, by the key that sealed them.
5. Retire the old master key — remove it from the DSN, or disable it in the KMS — only once the dry run counts no
   secret, and once you have settled how long the backups the old key still opens are kept: a backup taken before the
   rotation holds values sealed under the old key, and restoring it needs that key.

`KeyRotation::rotate($batchSize)` does the same from code and returns a `RotationReport` — the secrets re-sealed,
those skipped because another process changed them, those that did not open —; `KeyRotation::remaining()` counts what
is left, by key.

The component's own `key-management:rewrap-data-keys` rewraps the data keys of its data key store under another
client without touching the payloads; the bundle's envelopes carry their wrapped data key, so their rotation is the
command above.

## What it does not protect against

The encryption protects the values in the database, its backups and its replicas, from whoever reads them without the
keys. It does not protect them from the application itself, which can use the keys: code that can call `UserSecrets`
with a `SecretAccess` can read the secrets of the owner it names. Keep the keys out of the repository and apart from
the database backups, and build accesses only where the owner is known.

Revoking or removing a secret wipes it from the table, not from the backups taken before: there it stays sealed, and
readable by whoever holds those backups and the master key that sealed it, as long as that key lives. A secret is gone
from every copy only once the backups that hold it have expired or the master key that sealed it is destroyed.
