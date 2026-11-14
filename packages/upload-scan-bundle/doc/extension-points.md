# Extension points

## The scanner

`scanner: app.scanner` makes any service implementing `Phil\UploadScan\Contract\Scanner` the scanner of the bundle:
a cloud scanning API, several engines asked in turn. See the extension points of `phil/upload-scan` for the contract.

## The quarantine

`quarantine.service: app.quarantine` replaces the directory with a service implementing
`Phil\UploadScanBundle\Application\Contract\Quarantine`:

```php
interface Quarantine
{
    public function isolate(mixed $stream, string $name, ScanResult $result): string;
}
```

`isolate()` reads the stream from its current position, keeps it somewhere no visitor can reach — a private bucket, a
separate volume — and answers an identifier. It throws `QuarantineFailed` when it cannot keep the file; the bundle logs
it and still refuses the file. Never derive a path from `$name`: it is what the sender chose.

## The inventory of the quarantine

The administration page reads the quarantine through `Phil\UploadScanBundle\Application\Contract\QuarantineInventory`:

```php
interface QuarantineInventory
{
    /** @return list<QuarantinedFile> */
    public function files(int $limit, int $offset = 0): array;

    public function file(string $id): ?QuarantinedFile;

    public function delete(string $id): bool;
}
```

`files()` lists the records, the most recent first; `file()` answers `null` for an identifier the quarantine does not
give; `delete()` removes a file and its record for good, `false` when there was none. None of them returns what a file
holds. `FilesystemQuarantine` implements it; a quarantine of the application that implements it beside `Quarantine`
is used for the page without more configuration, and an alias of `QuarantineInventory` to another service wins.
Without an inventory, neither the page nor the purge command is registered.

## The rescan targets

A service implementing `Phil\UploadScanBundle\Application\Contract\RescanTarget` is found by its `name()` when a
`RescanFile` names it: see [Rescans](rescans.md).

## The events

`FileInfected` and `FileFlagged` are how the application reacts — an alert, a record marked pending, an account
reviewed: see [Quarantine and events](quarantine-and-events.md). `FileRescanned` tells a package that keeps files what
a rescan of its target found, without implementing the target: see [Rescans](rescans.md#filerescanned).
