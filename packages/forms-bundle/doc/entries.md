# Entries and retention

## What is kept

An `Entry` holds the values, one per field asked in the form's order, when it came, its language, and when it is
deleted, and what names the person it is about ([below](#who-an-entry-is-about)). **Nothing else** about the
visitor is kept: no IP address, no browser, no cookie. The spam protection reads the client to limit its rate, and
keeps nothing either.

```php
$repository->entries($form, limit: 50, offset: 0);   // newest first
$repository->entry($id);
```

## Who an entry is about

An access or erasure request names a person; their entries must be found without reading every one. An entry
keeps one of two things, or both:

- **The identifier of the person**, when they were known as they sent it: `CurrentSubject::current()` — by
  default the user signed in through Symfony Security, by `getUserIdentifier()` — is passed to
  `Submissions::submit()` and kept in `subject`, as `Entry::subjectKey()` makes it: as it is when it is 1 to 255
  bytes without whitespace or control characters, as `phil/privacy-bundle`'s `DataSubject` is; otherwise —
  `Jean Dupont`, a longer identifier — as `sha256:` and its SHA-256, so that such a person still sends the form. A
  request then names them by that key, `DataSubject::fromString(Entry::subjectKey('Jean Dupont'))`. An application
  whose requests name people otherwise aliases `CurrentSubject` to its own service.
- **The digest of an address**, for a visitor: a form designates one of its email fields
  (`Form::identifyBy('email')`), and the entry keeps `email_digest`, the HMAC-SHA256 of the address trimmed and
  lower-cased, under the key `phil_forms.digest_key`. The address is found however it was written, and cannot be
  tried against the digests without the key. **Changing the key** loses the entries already kept to requests by
  address, until their retention ends.

```php
$repository->about('member-7', $digester->digest('ada@example.com'));   // oldest first
```

With `phil/privacy-bundle`, `FormEntriesPrivacy` is the module `forms` of its access and erasure answers: the
request's subject is looked up both as an identifier and as an address. Exporting gives each entry's form, dates,
language and values; erasing deletes the entries and reports the store `phil_form_entry` as `deleted`, the same
report on a second run. The emails and webhooks already sent are outside the bundle.

## Sensitive values

The values of sensitive fields are sealed before the entry is kept. The default sealer is `KeyManagementFieldSealer`,
wired when `sealing.key` names a master key: it uses the envelope encryption of Symfony's KeyManagement component, and
the bundle has no cryptography of its own: a fresh data key wrapped by that master key of the KMS —
the component's default client, or the client `sealing.kms` names —, the value encrypted locally with AES-256-GCM,
the entry's id and the field's name authenticated with it, so that a sealed value copied to another entry or field
does not open. The entry names the sealed fields in `sealed`; a sealed value reads `phs1.<key>.<envelope>`, the
master key's name and the envelope in base64url, the envelope's first byte, 2, naming its format, so that it opens
under the key it was sealed with after `sealing.key` changes.

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[forms2026]=%env(FORMS_SEALING_KEY)%'

# config/packages/phil_forms.yaml
phil_forms:
    sealing:
        key: forms2026
```

A master key of a local `sodium://` client is 32 random bytes in base64url, kept in an environment variable or the
Symfony secrets vault; an application with a KMS of its own — AWS, Azure, Google Cloud, Vault — declares that client
instead and names one of its master keys. With `sealing.key` set and no KeyManagement component, or no client, the
container stops with a message that says what to change.

```php
$values = $sealedValues->reveal($entry);   // SealedValues: the values as they were sent
```

`SealedValues::reveal()` throws `UnreadableValue` when a value does not open, or when no sealer is installed. The
access answer of `phil/privacy-bundle` gives the values opened; a value that does not open is left out, its field
listed under `unreadable`.

Changing `sealing.key` seals new values under the new master key and does not seal the kept entries again: an old
master key stays in the KMS until the entries sealed under it are purged. While the KMS does not hold the master key
`sealing.key` names, sealing throws the component's `KeyNotFoundException` and the entry is not kept.

### Keeping sensitive values as sent

Without `sealing.key`, and without a `FieldSealer` of the application, no sealer is configured: a form with a
sensitive field is refused with `UnsealedSensitiveField`, its page not built and no entry taken, so that a value
the form calls sensitive never lies in the database in the clear by mistake. An application that accepts that risk
says so:

```yaml
# config/packages/phil_forms.yaml
phil_forms:
    allow_unsealed_sensitive: true
```

> **Warning:** with this option, the values of sensitive fields are kept as sent, readable by anyone who reads the
> database, its backups or its replicas. Set it only for a development environment, or where the database itself is
> encrypted and its access controlled for that purpose; prefer a local `sodium://` client of the KeyManagement
> component, which costs one environment variable.

Sensitive values are never emailed nor posted, sealed or not.

## Times

An entry's times are kept in UTC, and `submittedAt()` and `purgeAfter()` give them in UTC whatever PHP's default time
zone: an export shows the moment the entry came, not that wall clock in the application's zone.

## For how long

Each form keeps its entries a number of days, 1 to 3650, 365 by default: the time their purpose needs, as Québec's
Law 25 and the GDPR ask, and no longer. An entry's deletion date is set when it comes, so shortening a form's
retention leaves the entries already kept as they were.

## Deleting

```sh
bin/console phil:forms:purge --batch=500
```

The command deletes the entries whose time is over, a batch at a time until none is left; run it every night by
cron or a schedule. `Retention::purge($limit)` does the same from code. Removing a form through the repository
removes its entries and its counts of places with it; an entry removed gives back the places it took.
