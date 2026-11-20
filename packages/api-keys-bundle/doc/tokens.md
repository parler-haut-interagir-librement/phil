# Tokens

## The format

```text
acme_3kTMd9qXbW1z_Qx7bV0aLk2sR9uYc4mNp8wTe1hJg6dFo2B9fXk
└┬─┘ └────┬─────┘ └──────────────┬───────────────┘└─┬──┘
prefix  identifier             secret            checksum
```

| Part | What it is |
|---|---|
| Prefix | 2 to 16 lowercase letters and digits, from the configuration: the name a secret scanner looks for |
| Identifier | 12 letters and digits, drawn at random: it finds the row and appears in lists; it is not secret |
| Secret | 32 letters and digits, each drawn by `random_int()` from the secure source of `random_bytes()`, every character as likely as any other: about 190 bits |
| Checksum | The CRC-32 of everything before it, in 6 letters and digits |

Every token of a prefix has the same length, and the underscores keep it one word for a double click. The checksum
refuses a token mistyped, truncated or padded before any database lookup, and tells a scanner a real token from a
lookalike; it protects nothing, the secret does.

`TokenFormat::parse()` checks, in order, the length, the prefix and separators, the characters and the checksum, and
throws `MalformedToken` with a reason that never repeats the text.

## Owners

A token acts for an owner: a type and an identifier, `TokenOwner`. The type `user` is a member, loaded by the
firewall's user provider by the identifier given; a string where an owner is expected is a member. The configuration
declares the other types — an organisation, an application, a team — with the user provider that loads them and the
scopes their tokens may hold (see [Configuration](configuration.md) and [Authentication](authentication.md)).

```php
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

$tokens->create($user->getUserIdentifier(), 'Deploy script', ['projects:read']);       // a member
$tokens->create(new TokenOwner('organization', $organization->slug), 'Billing export', ['billing']);
```

A type is a lowercase letter followed by up to 31 lowercase letters, digits or `_`; an identifier is 1 to 180 bytes.
A member and an organisation of the same identifier own different tokens: every lookup by owner compares the type and
the identifier.

## What is stored

The row keeps the identifier, the owner's type and identifier, the name, the scopes, the keyed hash of the secret, the
creation and expiry, the last use, its anonymized address and the revocation. Never the secret: a copy of the table opens nothing without the key.

The keyed hash is the HMAC-SHA-256 of `<identifier>_<secret>` under the first key of `hash_key`, written in the shape
of the PHC string format so that it names its algorithm and the version of what is hashed:

```text
$hmac-sha256$v=1$DBwoY14DqHXh4Kk5eij6KIEjRULz1gJAfldLl/i37UY
 └────┬────┘ └┬┘ └───────────────────┬─────────────────────┘
  algorithm  version   the 32 bytes in Base64, without padding
```

A hash of another algorithm or version matches nothing: a later version is told apart from this one, and the rows
move to it as their tokens are rotated.

### The keys

`hash_key` is an ordered list: the first key hashes every new token, and every key verifies, so that the tokens
issued under a previous key keep working while it stays in the list. To rotate the key, put the new one first and keep
the previous ones after it; once the longest lifetime has passed since a key moved down, no token issued under it is
still valid, and it can be dropped. Every key is computed and compared on each verification, whichever one matches, up
to eight keys. The hash is the only thing the bundle keys: the CRC-32 of the token's text is a checksum, not a
signature, and the endpoint of GitHub's secret scanning verifies GitHub's signatures under GitHub's own keys. The text of
a token exists once, in the `IssuedToken` its creation returns, which refuses serialization and hides the text from
`var_dump()`.

## Creating

```php
use Phil\ApiKeysBundle\Application\ApiTokens;

$issued = $tokens->create($user->getUserIdentifier(), 'Deploy script', ['projects:read'], new DateTimeImmutable('+30 days'));

$issued->plainText;   // show it now, once
$issued->token->id(); // what the lists show
```

Without an expiry, the token lasts `default_lifetime` days. The expiry must be in the future and within
`max_lifetime` days; the scopes must be declared, and are kept in the order of the configuration; the name is 1 to
100 characters, trimmed. `TokenCreated` is dispatched once the token is saved.

## Verifying

`ApiTokens::verify()` is what the token handler calls: the shape and checksum offline, then the row by its identifier,
the secret against the hash under every key in constant time — an unknown identifier costs the same hashes —, the
revocation and the expiry. A refused token throws `InvalidCredentials`, whose `failure` says why for the logs:
`malformed`, `unknown`, `revoked` or `expired`. The client is never told which.

## The last use

A token used again within `last_used_throttle` seconds does not write its row: a script calling many times a second
writes once per interval. When it does, the date and the address — as the configured anonymizer gives it, or
nothing — are written by one statement that flushes nothing else of the request's unit of work, and `TokenUsed` is
dispatched.

## Revoking

```php
$tokens->revoke($id, $owner); // the owner's token, or TokenNotFound
$tokens->revoke($id);         // any token, for administrators
$tokens->revokeAll(new TokenOwner('application', $app->id)); // every active token of an owner
```

A revoked token is refused at once and keeps its row, with the date, until the purge. Revoking it again changes
nothing and dispatches nothing. `revokeAll()` revokes every active token of an owner — an organisation that closes, an
application that is uninstalled, a member who leaves —, up to ten thousand in a call, each announced by
`TokenRevoked`, and returns how many it revoked.

## Rotating

```php
$successor = $tokens->rotate($id, $owner);            // the old token revoked at once
$successor = $tokens->rotate($id, $owner, grace: 600); // the old token works ten more minutes
```

The successor has the same owner, name and scopes, and lasts as long as the old token did, within the longest
lifetime. A revoked or expired token is not rotated.

## Purging

`ApiTokens::purge($retention)` removes the tokens that expired or were revoked more than `$retention` days ago; the
command `phil:api-keys:purge-expired` runs it with `purge_retention`.
