# Issuing

## The parts

| Type | What it is |
|---|---|
| `Issuer` | Who issues: an absolute URL where its profile is published, a name, and optionally a web site, a contact address, an image and endorsements. |
| `Achievement` | What is recognized: an absolute URL, a name, a description, the narrative of its criteria, and optionally an image and endorsements. |
| `Recipient` | To whom: a DID, which becomes the subject's identifier, or an e-mail address or URL kept only as a salted SHA-256 hash. |

```php
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Recipient;

$issuer = new Issuer('https://example.org/issuer', 'Example', 'https://example.org', 'badges@example.org', 'https://example.org/logo.png');
$achievement = new Achievement('https://example.org/badges/speaker', 'Speaker', 'Gave a talk.', 'Give a talk of at least twenty minutes.', 'https://example.org/badges/speaker.png');

$recipient = Recipient::did('did:example:ada');
$recipient = Recipient::email($emailTheyGave, bin2hex(random_bytes(8)));   // salt of 8 characters or more
$recipient = Recipient::url('https://ada.example/', bin2hex(random_bytes(8)));
```

An e-mail address is trimmed and lowercased before it is hashed. The salt travels with the hash, in the credential:
it keeps the address from being found in a table of hashes, not from being confirmed by whoever knows it.

URLs and identifiers are checked: an HTTP URL is absolute and holds no character an IRI refuses, a DID has a method
in lowercase. A value that does not hold together raises `InvalidBadge`.

## The credential

```php
use Phil\OpenBadges\CredentialIssuer;

$issuer = new CredentialIssuer($issuerProfile, $signer, 'https://example.org/issuer#key-1');

$credential = $issuer->credential($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt, $validUntil, $status, $endorsements);
$token = $issuer->issue($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt);
```

- The identifier is a `urn:uuid:` in lowercase or an absolute HTTP URL.
- The credential is valid from the moment given — the award's — and until the moment given, if any, after it. Both
  are written in UTC, to the second (`2026-11-07T15:00:00Z`).
- `$status` is a `StatusListEntry`, for a credential that can be revoked: see [Revocation](revocation.md).
- `$endorsements` are signed `EndorsementCredential`s: see [Endorsements](endorsements-and-clr.md).

`credential()` returns the credential unsecured, an array ready for a Data Integrity proof; `issue()` returns it as a
JSON Web Token signed by the issuer's key, whose identifier — the third argument of the constructor — goes in the
token's header. See [Securing](securing.md).

The recipient asks for their credential; the application gives it to them, as a token, a JSON document or a baked
image ([Baking](baking.md)).
