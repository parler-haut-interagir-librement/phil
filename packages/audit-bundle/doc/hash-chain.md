# The hash chain

**State: implemented, except the erasure operation.** This page states the design and, section by section, what
the bundle does of it.

## What it is, and what it is not

An **append-only**, hash-chained audit trail that stays compatible with the erasure of personal information.

It is a log, not a source of truth: it is **not event sourcing**, and no state is rebuilt from it.

## Threat model

The trail is written against **an administrator with access to the database** — the person an audit trail has
to hold up against.

- Chaining makes a change to one row detectable: every later row's hash depends on it.
- Chaining alone is undone by anyone who can rewrite the **whole** chain from the altered row onwards. Only an
  anchor written outside the database, to storage that administrator cannot rewrite, makes that rewrite
  detectable ([anchoring.md](anchoring.md)).
- Without anchors, a full rewrite of the chain goes undetected. This is the first thing to know about the bundle.

## The design

### Hash-chained rows

Each row carries the hash of its payload and the hash of the previous row. The row hash is taken over the
**canonical serialization** of the row's fields ([serialization-door.md](serialization-door.md)): the non-personal
fields and the commitments of the personal fields, **never** their plaintext
([commitments-and-erasure.md](commitments-and-erasure.md)).

What exists: `AuditRow`. Its hash is SHA-256, in hexadecimal, of its segment, sequence, kind, instant (UTC, to the
microsecond), serialization version, the previous row's hash, its five facts — action, actor, subject type, subject
identifier and tenant, an empty line for each one missing ([reading.md](reading.md)) — and the payload, each on a line
of its own and the payload last, so that no field runs into the next. None of the fields before the payload may hold a
line break: a row whose segment, version or facts hold one, or whose facts are not identifiers, is not intact whatever
its hash, since a line break could move a value into the next line and keep the hash. A segment's first row links to
`AuditRow::GENESIS`, 64 zeros. `AuditTrail::record($segment, $fields, $facts)` serializes the fields with the
application's `CanonicalSerializer`, stamps the facts with the current tenant when they name none, links the row to
the head of its segment and appends it; a row another process appended first (`ChainConflict`, the
primary key `(segment, sequence)` refusing it) is retried on the new head, three times in all. The table keeps the
instant as text, a date type would round it and break the hash.

### Serialization version in every row

Each row records the version of the serialization it was hashed under (`CanonicalSerializer::version()`), and the
row hash covers that version. A later serialization is a new version beside the old one, never a replacement.

What exists: each row's `version`, from `CanonicalSerializer::version()`, hashed with the row. Verification hashes
the payload **as stored**: it does not read the version, and it does not serialize the fields again, so a row
written under any version keeps verifying without its serializer.

### Erasure keeps the hash

Erasure deletes a personal field's plaintext and its salt and keeps the commitment. Because the hash covers the
commitment, the row hash does not change, every anchor still verifies, and the payload legitimately reads
`[erased]`.

What exists: `Commitment` and `Salt`, which make this possible. The erasure operation itself is the
application's for now.

### A discontinuity row after every restore

A point-in-time restore rewinds the table past rows that anchors have already committed to. After every restore,
a **discontinuity row** records the restore and is anchored immediately, so the gap between the last anchor and
the restored head is explained rather than reported as tampering. A discontinuity row is only an explanation; it
does not split the segment, and the verification of the rows before and after it is the same.

What exists: `AuditTrail::recordDiscontinuity($segment, $reason)`, a row of kind `discontinuity`, to anchor at once
(`phil:audit:anchor`).

### Verification per segment

Verification runs per **segment**, the `segment` of the rows, checking every row of the segment and its boundary
hashes against its anchors. An unexplained break stays exactly what it must be: an alarm. Verification is meant to
fail loudly.

What exists: `ChainVerifier`, run by `TrailVerification` and `phil:audit:verify`. For each segment it recomputes
every row's hash (`the row was changed after it was written`), checks every link (`the row does not link to the row
before it`) and the sequence (`row N is missing`), and checks every anchor read back through `AnchorReader`: its
first hash must be the chain's first (`the anchor of … does not start where the chain starts`), and its head hash
must be a row's (`no row has the head hash anchored at …`). In the segment of a site, every row must carry that
site's tenant (`the row is not of the site the segment belongs to`, [sites.md](sites.md)).

A lost head is **explained** only by a discontinuity row recorded after the lost anchor's period and no later than
the next anchor whose head the chain still has, when that next anchor covers it — its head is the discontinuity
row or a row after it. The discontinuity was then anchored, so it existed before that anchor was written. An
explained break is still printed, with the discontinuity's sequence and `explained by this discontinuity`, and the
segment `holds, with N explained break(s) to review`: a rewrite followed by a forged discontinuity looks the same as
a restore, and only a person comparing it with the restores actually made can tell them apart. So
`phil:audit:verify` fails on an explained break too, and says how many are left to review; once someone has compared
them with the restores, `--accept-explained` lets it succeed when every break is explained. A break that no
discontinuity explains fails with or without the option. Once a segment has
anchors, a discontinuity no anchor covers is an alarm (`the discontinuity is covered by no anchor`): anchor right
after recording one.

The rows are read once, in order. What verification keeps is the first hash, the sequence at which each anchored
head was found and the discontinuity rows, so its memory grows with the anchors and the restores, not with the
rows. The command prints every break and exits with a failure on any alarm: watch its code.

## Not started

Erasure as an operation of the bundle: plaintext and salt deleted, commitment kept, a free-text reason field redacted
whole.

## What belongs to the application

- anchor storage, under object lock in compliance mode or the equivalent;
- table partitioning and retention periods;
- the database roles that may write and delete;
- the list of audited events and the list of fields declared personal;
- the alert on a verification failure;
- the canonical serialization, until the bundle ships a frozen version of its own.
