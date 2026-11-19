# Compliance

`LicenseCompliance::check($declaration)` returns a `ComplianceReport`: no violation, or each `Violation` in the order
found, with its `ViolationCode`, the license concerned, and the resource by its position and title.

| Code | When |
|---|---|
| `no_declaration` | The subject has no declaration (the export guard only). |
| `unknown_license` | The work or a resource names a code the catalog does not hold. |
| `missing_license_text` | The license asks for its full text, or has neither address nor reference, and there is no license text. |
| `missing_notice` | The license asks for the work's copyright notice. |
| `missing_reserved_material` | The license asks for the Reserved Material. |
| `missing_product_identity` | The license asks for the Product Identity. |
| `missing_designated_content` | The license asks for the open content to be identified. |
| `missing_credit` | A resource's license asks for credit, and the attribution has neither author nor notice. |
| `missing_resource_notice` | A resource's license asks for its notice to be kept, and the attribution has none. |
| `missing_changes` | A resource's license asks for the changes to be indicated, and the attribution says nothing of them; an unchanged resource says so. |
| `share_alike_conflict` | A resource is under a share-alike license other than the work's. |

`LicenseNames::violation($violation, $locale)` and `phil_licensing_violation()` give the message, `violation.<code>`
of the domain `phil_licensing`, the license named in the reader's language. What the check cannot see — that the
notices and the changes stated are true — stays the publisher's.

## The export guard

An application calls `ExportGuard` before it exports a work — a PDF, an EPUB, a print file:

```php
$exportGuard->assertExportable($book);   // throws ExportBlocked, with ->subject and ->report

$report = $exportGuard->check($book);    // or reads the report
```

A subject without a declaration is refused with `no_declaration`. After the check, the guard dispatches
`ExportChecking` through the PSR-14 dispatcher: a listener adds the violations of the application's own rules, and
the export is blocked by them as by the others.

```php
#[AsEventListener]
final class RequireAttributionPage
{
    public function __invoke(ExportChecking $event): void
    {
        if (!$this->layout->includesAttributionPage($event->subject)) {
            $event->addViolation(new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page'));
        }
    }
}
```

## Printing

With `phil/paged-media-bundle` installed, the guard runs before each print that names its work, and the strict
printing refuses a print that names none: see [Printing](printing.md).

## The voter

With SecurityBundle, `is_granted('PHIL_LICENSING_EXPORT', subject)` is denied while the guard finds a violation, each
violation's code a reason of the vote. It says nothing of who may export: the application's voters decide that, and
the access decision strategy combines them.
