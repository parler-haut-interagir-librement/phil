# One chain per site

An application that serves several sites from one database — one tenant each, as `phil/tenancy-bundle` resolves
them — can keep one audit trail for all of them or give each site a chain of its own. With one chain, the rows of
every site interleave in the same segments: each row names its tenant, reads are held to it, but verifying a segment
reads the rows of every site, and a site cannot be handed its own chain to check. With site segments, every site is
chained, anchored and verified on its own.

## Turning it on

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    site_segments: true
```

`AuditTrail::record($segment, $fields, $facts)` then records a row whose tenant is known — given in its `Facts`, or
the current one of the `TenantScope` — in the segment of that tenant: the segment you name, followed by `.t-` and the
first 32 hexadecimal digits — 128 bits — of the SHA-256 of the tenant reference.

```php
$trail->record('orders', ['total' => '10.00'], Facts::of('order.paid', $actor, Subject::of('order', '42')));
// in the tenant "shop-a": segment "orders.t-<32 hex digits>", sequence 1 of that site
// outside any tenant: segment "orders", shared by every site
```

`AuditTrail::segmentFor('orders', 'shop-a')` gives the name without recording anything. The tenant reference is not
in the name, since it may be long or hold characters a segment refuses; every row of the segment carries it as its
tenant fact. A segment name you give is at most 29 characters when site segments are on
(`SiteSegment::MAX_BASE_LENGTH`), so that the site's name fits in the 64 characters of the table's column.

The digest keeps two sites apart even when users choose the tenant references: naming the segment of a given tenant
takes about 2^128 tries, and finding two references of one segment, both chosen, about 2^64. It takes no key, on
purpose: a segment's name is hashed into each of its rows, so it must never change, whatever secret the application
rotates, and anyone holding the trail can check which tenant a segment belongs to:

```bash
printf %s 'shop-a' | sha256sum | cut -c1-32
```

A restore of one site's data is recorded in that site's segment by naming the tenant:

```php
$trail->recordDiscontinuity('orders', 'restored from the backup of 2026-11-11', 'shop-a');
```

Without a tenant, the discontinuity goes to the shared segment, as it does without site segments.

## What verification adds

`ChainVerifier` reads the site from the segment's name: in a site segment, every row must carry that site's tenant.
A row with another tenant, or with none, is an alarm, `the row is not of the site the segment belongs to`: a row
moved from one site's chain into another's shows, even when its hash and its link were recomputed. The digest of
each tenant met is taken once per segment, not once per row.

`phil:audit:verify --tenant=shop-a` verifies the segments of that site only. `TrailVerification::verify(null,
'shop-a')` does the same from code. Anchoring is unchanged: each site segment is anchored like any other segment, so
each site's anchors can be written to a destination of its own by an `AnchorWriter` that reads the segment name.

## What a tenant sees

In a tenant, `ChainStatus::segments()` lists that site's segments only, and `ChainStatus::verify()` answers a segment
of another site, or a shared one, as a segment without rows: no head, no breaks, nothing read. `AuditLog` already
holds every read to the current tenant ([reading.md](reading.md)). Outside any tenant, every segment is listed; a
`SegmentStatus` names its site with `site()` — the tenant its head row carries — and the segment you named with
`base()`, which is how `phil/audit-viewer` groups the chain page by site.

## `SiteSegment`

| Method | Answer |
|---|---|
| `SiteSegment::of(string $segment, string $tenant): string` | The site segment; `InvalidValue` for a segment over 29 characters, a segment that is a site segment already, or a tenant that is not a reference |
| `SiteSegment::isSite(string $segment): bool` | Whether the name ends in `.t-` and 32 lower-case hexadecimal digits |
| `SiteSegment::belongsTo(string $segment, string $tenant): bool` | Whether it is the segment of that tenant, compared in constant time |
| `SiteSegment::base(string $segment): string` | The segment named, without the site's digest |

Sixty-four bits of digest keep two tenants of one application from sharing a segment by accident; verification would
show it if they did, since the rows of the second tenant are refused in the first one's segment.

## Turning it on later

Site segments change where new rows go, never where old ones are. A trail kept in shared segments before
`site_segments` is turned on keeps those segments, verified as before; the rows recorded afterwards start each
site's chain at sequence 1. Turning it off sends new rows back to the shared segments.
