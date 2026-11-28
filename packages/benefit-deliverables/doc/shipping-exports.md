# Shipping exports

`ShippingExports` writes, for a benefit that ships an object, the file a shipper needs to send the deliverables still
to be served in a period.

```php
$result = $exports->export($period, 'postcard', 'staff-7', $now);

$result->record->rows;            // how many rows the file holds
$result->record->withoutAddress;  // how many members were left out for want of an address
$result->token;                   // the token of the download link
```

## What the file holds, and never holds

A CSV file with one row per deliverable, and these columns only:

| Column | From the address |
|---|---|
| `name` | The given, additional and family names; the organization when no person is named |
| `address_line_1`, `address_line_2`, `address_line_3` | The lines of the address |
| `city` | The locality |
| `region` | The province, the state, the region |
| `postal_code` | The postal code |
| `country` | The ISO 3166-1 alpha-2 code of the country |
| `deliverable` | The code of the benefit |

**Never** the member's email address, identifier or tier, nor their telephone number: the file goes to a third party,
and holds what the label needs and nothing more.

- **Formulas guarded**: the file is written by `phil/spreadsheet`, which keeps as text, behind an apostrophe, a value
  a spreadsheet would run as a formula (`=`, `+`, `-`, `@`, a tab, a carriage return).
- **Excel-friendly**: UTF-8 with a byte order mark, lines ending in CRLF.
- **Done deliverables are left out**: an export lists what remains, so that an export made after part of a period was
  shipped holds the rest only.
- **A member without an address is left out** and counted in `withoutAddress`; nothing else is said about them.
- **Bounded**: the members are read by pages of 500, the file written as it goes, and an export holds 50,000 rows at
  most by default (`$maxRows`).

A benefit that ships nothing, a mention or a game, has no export: `ExportRefused::shipsNothing()`.

## Where the addresses come from

The application implements `Contract\DeliveryAddressProvider`: `addressesOf($members)` returns the `Phil\Address\Address`
of each member who has one. It may read the delivery address of a subscription, a member's profile or a form; the
package keeps none.

## The journal

Each export is recorded through `Contract\ExportJournal` as an `ExportRecord`: its identifier, the period and the
benefit, **who asked for it and when**, **how many rows**, how many members had no address, until when its file is
kept, and when the file was erased. The record outlives its file, as the trace of what left for a shipper.

## The signed link and the expiry

The token of `ExportResult` is `<id>.<expiry>.<signature>`, the signature an HMAC-SHA256 of the identifier and the
expiry under a key that `DownloadLinks` derives from its secret with HKDF and the label
`phil_benefit_deliverables.download_link`, so that no other use of the secret yields the same signatures. The application puts it in a download route of its own, behind the
authorization of its team:

```php
$stream = $exports->download($token, $now);   // the file, open for reading
```

- The link and the file last `$ttlSeconds`, an hour by default, a minute to a day.
- A token of another shape, a signature that does not match, compared in constant time, an expiry passed, an export
  unknown or a file erased all give the same `ExportRefused::unavailable()`, so that a link tried by guess tells
  nothing.
- The secret is 32 bytes or more, kept with the application's secrets; changing it voids every link.

## The purge

```php
$exports->purge($now);   // erases the files of the exports expired, up to 500
```

`purge()` erases the file of each export expired and notes it in the journal. Run it from a scheduled task, every
hour for instance. Run again, it finds nothing left.

## Where the files wait

`Contract\ExportFileStore` writes, opens and erases the files. `Export\LocalExportFileStore` keeps them in a directory
of the server as `<id>.csv`, readable by the owner only (`0600`), written to a temporary file then renamed, so that a
reader never sees half a file and a failure leaves none. Keep the directory **outside the web root**: a file is handed
out through its signed link only. An application whose servers share no disk implements the port over its own private
storage.
