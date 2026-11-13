# Batches

## Creating a batch

```php
$created = $codes->create('Album cards, autumn fair', 500, new DateTimeImmutable('2027-12-31 23:59:59'));
$created->batch->id;     // 32 hexadecimal digits, what the application unlocks from
$created->batch->name;   // "Album cards, autumn fair"
```

`create()` takes a name of 1 to 120 characters, trimmed, a size of 1 to 1,000,000, and an optional expiry after now.
It draws distinct codes, hashes each under the active key, and hands the batch and the hashes to the store in one
transaction. A batch of 100,000 codes takes about half a second; see [Testing](testing.md).

The `Batch` keeps its identifier, name, size, creation, expiry and revocation. What a code unlocks — a file, an album,
a ticket — is the application's: it keeps the batch identifier beside the product.

## The codes, once

The `CreatedBatch` holds the codes in clear for one use. Either write them as CSV:

```php
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;

$created->writeCsv(new SplFileObject('/secure/cards.csv', 'x'), new RedemptionLinks(Url::fromString('https://shop.example/redeem')));
```

```text
code,link
7KQ2-M9XD-H4TB-H,https://shop.example/redeem?code=7KQ2M9XDH4TBH
...
```

or take them as a list, for a print template or a mail merge:

```php
$codes = $created->takeCodes();               // ["7KQ2-M9XD-H4TB-H", ...]
$canonical = $created->takeCodes(false);      // ["7KQ2M9XDH4TBH", ...]
```

The second call of either throws `CodesAlreadyTaken`, and `hasCodes()` says whether they are still there. Nothing
else can give them: the store holds only their hashes. Write the CSV to a file before sending it anywhere, so that a
failed upload does not lose the batch; a batch whose codes were lost is revoked and created again.

The object refuses to be serialized or cloned and shows `"500 codes, hidden"` to `var_dump()`, so that a cache, a
session, a queue or a log does not keep the codes by accident.

## Prefilled links

`RedemptionLinks` builds the address of the redemption page with a code filled in, on `phil/url`:

```php
$links = new RedemptionLinks(Url::fromString('https://shop.example/redeem?from=card'), 'code');
$links->for('7KQ2M9XDH4TBH');   // https://shop.example/redeem?from=card&code=7KQ2M9XDH4TBH
```

The parameter is 1 to 32 lowercase letters, digits and underscores; the page has no fragment. Opening the link fills
the form; the person still confirms. Serve the page with `Referrer-Policy: no-referrer`, so that a link out of it does
not carry the code to another site.

## Revoking and counting

```php
$codes->revoke($batchId);   // the number of codes revoked: those not yet redeemed
$codes->counts($batchId);   // BatchCounts: total, redeemed, revoked, unused()
$codes->batch($batchId);    // Batch, with revokedAt
```

A revoked batch redeems nothing more; the codes already redeemed stay redeemed. Revoking twice revokes nothing the
second time. An unknown batch throws `UnknownBatch`.
