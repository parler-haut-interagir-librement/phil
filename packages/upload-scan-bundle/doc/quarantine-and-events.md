# Quarantine and events

## The quarantine

An infected file is copied to the quarantine before it is refused, for an operator to look at — to confirm a threat,
or to release a false positive by hand. `FilesystemQuarantine`, the default, keeps it in
`%kernel.project_dir%/var/quarantine`:

```text
var/quarantine/                              drwx------
├── 9f0c…e2a1.quarantine                     -rw-------   the file, byte for byte
└── 9f0c…e2a1.json                           the record
```

```json
{
    "name": "chapter-3.docx",
    "signature": "Doc.Dropper.Agent-1234",
    "quarantined_at": "2026-11-08T10:30:00+00:00"
}
```

- The identifier is 32 random hexadecimal digits. Nothing the sender chose becomes a path: the name they gave is only
  a field of the record, its control characters removed, cut to 255 bytes.
- The directory is created readable by its owner only, each file read and written by its owner only.
- A directory under `public/` is refused when the container is built, and no route of the bundle serves what the
  quarantine holds: an infected file is never downloaded from the site. Keep the directory out of backups that are
  restored to places the web server serves.
- An operator deletes the files dealt with on the administration page, below, and the purge command deletes those
  kept longer than the retention.

When the quarantine cannot keep a file — the disk full, the directory not writable — the failure is logged as an error,
`quarantineId` is `null`, and the file is still refused.

## The administration page

With Twig and `symfony/security-csrf`, the bundle registers `QuarantineAdminController`, the page where an operator
reads the quarantine and deletes the files dealt with. Import its routes behind the application's firewall:

```yaml
# config/routes/phil_upload_scan_admin.yaml
phil_upload_scan_admin:
    resource: '@PhilUploadScanBundle/config/admin_routes.php'
    prefix: /admin/quarantine
```

| Route | Method and path | |
|---|---|---|
| `phil_upload_scan_quarantine` | `GET /` | The files, the most recent first, `page_size` at a time: `page` |
| `phil_upload_scan_quarantine_delete` | `POST /{id}/delete` | Deletes a file and its record for good, with the CSRF token `phil_upload_scan_quarantine` |

Each file shows the name it was sent under, the signature that condemned it, when it was kept, its size and its
identifier — the one `FileInfected` carried. What a file holds is never shown and never served: an operator who must
look at one reads it on the server. A deletion without a valid CSRF token answers `400`, one of an identifier the
quarantine does not hold `404`; a deletion that goes through is logged at the `notice` level with its identifier and
sends the browser back to the list with a `303`.

Every page asks the application's security for `admin.role`, `ROLE_ADMIN` by default — a role, or the attribute of a
voter —; anything else answers `403`, and so does every page when Symfony's security is not installed. The page
extends `admin.layout`, a template with the `title`, `stylesheets`, `javascripts` and `content` blocks; the default is
a bare HTML page that asks robots not to index it. The bundle's template, `@PhilUploadScan/admin/quarantine.html.twig`,
is plain HTML with a `phil-upload-scan-quarantine` class, and may be overridden under
`templates/bundles/PhilUploadScanBundle/`.

The page reads the quarantine through `QuarantineInventory`, which `FilesystemQuarantine` implements. A quarantine of
the application that implements it too is read the same way; otherwise the application aliases `QuarantineInventory`
to a service of its own, or the page is not registered. See [Extension points](extension-points.md).

In an EasyAdmin back office, `QuarantineAdmin::menuItem()` links the dashboard's menu to the page:

```php
use Phil\UploadScanBundle\Infrastructure\EasyAdmin\QuarantineAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Security');
    yield QuarantineAdmin::menuItem();   // "Quarantine", fa fa-biohazard; the label may be a TranslatableInterface
}
```

Point `admin.layout` to a template of the back office so that the page keeps its look.

## The retention

`phil:upload-scan:purge-quarantine` deletes for good the files kept longer than `quarantine.retention_days`, 30 by
default, with their records, and logs each deletion at the `notice` level with its identifier. It reads the quarantine
through `QuarantineInventory`, a hundred records at a time, and is registered with the Console component when the
quarantine can be listed.

```sh
bin/console phil:upload-scan:purge-quarantine              # the configured retention
bin/console phil:upload-scan:purge-quarantine --days=7     # another retention, for this run
bin/console phil:upload-scan:purge-quarantine --dry-run    # counts, deletes nothing
bin/console phil:upload-scan:purge-quarantine --max=500    # at most 500 files in this run
```

A run deletes at most `--max` files, 10,000 by default, so that a quarantine left to grow empties over several runs. A
file kept exactly the retention is kept. Run it daily, from cron or from a schedule of Symfony Scheduler:

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('@daily', new RunCommandMessage('phil:upload-scan:purge-quarantine'));
```

`QuarantinePurger` does the same from the application's code: `purge($days, $max, $dryRun)` returns the files deleted,
or counted on a dry run.

## `FileInfected`

Dispatched on the PSR-14 event dispatcher for every infected file, whether a validator, an integration or a rescan
found it:

| Property | Value |
|---|---|
| `name` | The name the sender gave the file. Never trust it as a path. |
| `signature` | The signature the antivirus found, printable ASCII. |
| `context` | Where it came from: `upload`, a constraint's `context`, `media`, `document_import`, `rescan.<target>`. |
| `quarantineId` | The identifier in the quarantine, or `null`. |

```php
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AlertSecurityTeam
{
    public function __invoke(FileInfected $event): void
    {
        $this->notifier->send(new Notification(\sprintf('%s found in an upload (%s), quarantined as %s.', $event->signature, $event->context, $event->quarantineId ?? 'nothing')));
    }
}
```

The event carries nothing about the person who sent the file; a listener that needs it reads it from the request or
the security token, and keeps it only as long as the application's privacy policy allows.

## `FileFlagged`

Dispatched when a file the scanner could not scan is accepted under `on_error: accept_and_flag`: `name`, `reason` (why
it was not scanned) and `context`. A listener typically marks the record of the file as pending and dispatches a
[`RescanFile`](rescans.md) for it, delayed by a few minutes.

A file scanned again on a worker is followed by [`FileRescanned`](rescans.md#filerescanned), once its target resolved
the new inspection.

Without an event dispatcher in the container, no event is dispatched; the inspection still says what happened.
