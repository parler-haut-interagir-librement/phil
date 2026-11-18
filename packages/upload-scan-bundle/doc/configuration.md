# Configuration

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    scanner: clamd                      # clamd, fake, null, or the id of a Scanner service
    clamd:
        address: tcp://127.0.0.1:3310   # or unix:///run/clamav/clamd.ctl
        timeout: 30.0                   # each read or write, in seconds
        connect_timeout: 5.0            # the connection, in seconds
        chunk_bytes: 65536              # the size of each chunk sent
    max_bytes: 26214400                 # the largest file scanned
    on_error: reject                    # or accept_and_flag
    quarantine:
        enabled: true
        directory: '%kernel.project_dir%/var/quarantine'
        tighten_open_directory: true    # false refuses a directory open to other users instead of narrowing it
        service: null                   # the id of a Quarantine service, instead of the directory
        retention_days: 30              # how long phil:upload-scan:purge-quarantine keeps a file
    rescan:
        transport: null                 # the Messenger transport RescanFile is routed to, such as async
    admin:
        enabled: true                   # false leaves the quarantine's page, and its CSRF check, out
        role: ROLE_ADMIN                # the role, or a voter's attribute, that opens the quarantine's page
        layout: '@PhilUploadScan/admin/layout.html.twig'
        page_size: 50                   # 1 to 500 files a page
    integrations:
        media: auto                     # auto, on or off
        monitoring: auto
```

## `scanner`

| Value | Scanner |
|---|---|
| `clamd` | `ClamdScanner`, with the `clamd` keys. |
| `fake` | `FakeScanner`: the EICAR test string is infected, everything else clean; for the test environment. |
| `null` | `NullScanner`: every file clean. Only where scanning is knowingly off. |
| any other string | The id of a service implementing `Phil\UploadScan\Contract\Scanner`. |

The `Scanner` interface is an alias of the chosen service.

## `clamd`

The address is checked when the container is built: `unix://<path>` or `tcp://<host>:<port>`. The documentation of
`phil/upload-scan`, in its `doc/clamd.md`, describes the protocol and what each timeout bounds.

## `max_bytes`

The largest file sent to the scanner, 25 MiB by default. A larger file cannot be scanned: it is rejected, or accepted
and flagged, as `on_error` says. Keep it at or below the `StreamMaxLength` of clamd, and at or above the largest upload
the application accepts.

## `on_error`

What becomes of a file the scanner could not scan — clamd down, too slow, the file too large:

- `reject`, the default: the file is refused. An attacker who slows the antivirus down cannot slip a file past it.
- `accept_and_flag`: the file is kept, `FileFlagged` is dispatched, and `Inspection::flagged()` is true; the
  application keeps it out of sight until a [rescan](rescans.md) clears it.

An infected file is refused under both.

## `quarantine`

Infected files are copied to `directory`, created readable by its owner only. A directory that already exists and is
open to its group or to other users is narrowed to `0700` before a file is kept in it; with
`tighten_open_directory: false`, or when it cannot be narrowed, the file is refused and not kept. A directory under
`public/` of the project is refused when the container is built: the web server would serve what it holds. `service` names a `Quarantine` of the
application instead; `enabled: false`, or `quarantine: false`, refuses infected files without keeping them.
`retention_days`, 30 by default and at least 1, is how long the purge command keeps a file. See [Quarantine and
events](quarantine-and-events.md).

## `rescan`

`transport` names the Messenger transport `RescanFile` is routed to: the bundle adds the route to the configuration of
FrameworkBundle, before the application's own, so a route the application gives the message still wins. The name is 1
to 64 letters, digits, `_`, `.` or `-`; `null`, the default, leaves the routing to the application, and a message not
routed is handled as it is dispatched. See [Rescans](rescans.md).

## `admin`

The administration page of the quarantine, wired with Twig and `symfony/security-csrf` once its routes are imported:
`role` opens it, `layout` is the template it extends, and `page_size` the files listed a page. See
[Quarantine and events](quarantine-and-events.md#the-administration-page).

Its deletions require the stateless CSRF token `phil_upload_scan_quarantine`, by default and with nothing to turn on;
FrameworkBundle's `csrf_protection` must then stay on, or the container does not build. `enabled: false` leaves the
page, its controller and its check out, for an application that installed the CSRF component for its own forms and
turns the protection off. See [CSRF](csrf.md).

## `integrations`

`auto` turns an integration on when its bundle is registered; `on` requires the bundle, and fails the build without
it; `off` leaves it alone. See [Integrations](integrations.md). The health check is wired only for the `clamd` and
`fake` scanners.
