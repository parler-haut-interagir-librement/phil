# Anchoring

**State: implemented.** `AnchorWriter` and `AnchorReader` are ports; `Anchoring` and `phil:audit:anchor` write the
anchor of every segment; `JsonLinesAnchorStore` is an adapter for development and tests.

## Why anchors

Chaining alone is undone by anyone who can rewrite the whole chain. A periodic **anchor** — the boundary hashes
of a period, written outside the database — makes that rewrite detectable, **and only if its destination is
append-only**: an anchor written with a credential that can also delete it is rewritten by the same administrator
who rewrites the chain.

Acceptable destinations, in decreasing order of strength:

1. object storage under **object lock in compliance mode** (retention nobody can shorten or remove, the account
   root included);
2. other WORM storage;
3. at the very least, a credential that holds write access **without** delete or overwrite, on storage that
   versions objects.

Keep the anchor credential separate from the one that writes backups and from anything stored on the application
host.

## The port

```php
namespace Phil\AuditBundle\Domain\Contract;

interface AnchorWriter
{
    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void;
}
```

| Parameter | Meaning |
|---|---|
| `$segment` | The identifier of the anchored segment: a partition, or a segment between two discontinuities |
| `$periodEnd` | The end of the anchored period |
| `$firstHash` | The hash of the first row of the period, to verify the boundaries |
| `$headHash` | The hash of the last row of the period |

The method returns nothing: a write that did not reach append-only storage must throw.

The hash encoding (raw bytes or hex) is not fixed by the port; the application's chain decides it and the
adapter must store it losslessly.

## When to anchor

- periodically (for example daily), per segment;
- **immediately after a discontinuity row**, written after every restore ([hash-chain.md](hash-chain.md)).

```bash
bin/console phil:audit:anchor
```

`Anchoring::anchor($periodEnd)` writes, for every segment with a row, its first and head hashes (hexadecimal SHA-256)
and prints them; schedule the command every day, and run it at once after `recordDiscontinuity()`.

## The file store

`JsonLinesAnchorStore` appends one JSON line per anchor to a file, and reads them back: set `anchor_file` and it is
both writer and reader. **It is append-only only if the file lives on storage the database's administrator cannot
rewrite** — a mount under object lock, a WORM volume —; on the application host it makes a full rewrite no harder.
A write that does not reach the file throws.

See [extension-points.md](extension-points.md) for the invariants an adapter keeps and an example.

## Without anchors

The chain still detects a change to one row by someone who does not recompute every later hash. It does **not**
detect a full rewrite. Do not present an unanchored trail as tamper-proof.
