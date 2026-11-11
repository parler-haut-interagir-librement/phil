# Revocation

A credential issued by mistake or obtained by fraud is revoked through a **Bitstring Status List**: the issuer
publishes a list of bits, one per credential, and sets the bit of a revoked credential. The credential says where its
bit is.

## The entry a credential carries

```php
use Phil\OpenBadges\Status\StatusListEntry;

$status = new StatusListEntry('https://example.org/status/1', $index);            // revocation
$status = new StatusListEntry('https://example.org/status/2', $index, 'suspension');

$issuer->credential($achievement, $recipient, $id, $awardedAt, status: $status);
```

The application gives each credential its own index in a list, and keeps it. The entry becomes the credential's
`credentialStatus`, a `BitstringStatusListEntry`.

## The list

```php
use Phil\OpenBadges\Status\StatusList;
use Phil\OpenBadges\Status\StatusListIssuer;

$list = StatusList::of($revokedIndexes);       // at least 131,072 bits
$credential = new StatusListIssuer($issuerProfile)->credential('https://example.org/status/1', $list, 'revocation', $now, $until);
```

The list holds at least 131,072 bits, so that it does not tell how many credentials were issued, and at most
16,777,216, a multiple of 8. Its first index is the leftmost bit of the first byte. It is published GZIP-compressed,
in multibase base 64 URL (`u…`), in a `BitstringStatusListCredential` the issuer secures like any credential and
serves at the list's URL. A short `validUntil` tells verifiers how long they may keep it.

## Reading a status

```php
$revoked = new StatusListIssuer($issuerProfile)->applies($verified->status(), $verifiedStatusListCredential);
```

`applies()` takes the status list credential, verified by the caller, checks that it is the entry's list and issued by
the issuer, decodes the list — refusing one that inflates past its bounds — and reads the bit.
