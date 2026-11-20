# Validation

## `#[CleanFile]`

```php
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class CoverUpload
{
    #[Assert\NotNull]
    #[Assert\Image(maxSize: '10M')]
    #[CleanFile(context: 'cover')]
    public ?UploadedFile $image = null;
}
```

The constraint takes an `UploadedFile`, a `File` or a local path. The file is scanned once, through the
`UploadScanner`, so an infected file is quarantined and `FileInfected` dispatched as the constraint refuses it.

| Outcome | Violation | Code |
|---|---|---|
| Clean | none | |
| Infected | `infectedMessage`: "This file contains a threat and was refused." | `CleanFile::INFECTED_ERROR` |
| Not scanned, `on_error: reject` | `notScannedMessage`: "This file could not be checked for threats. Please try again later." | `CleanFile::NOT_SCANNED_ERROR` |
| Not scanned, `on_error: accept_and_flag` | none; `FileFlagged` is dispatched | |

`null` and `''` pass, as with every constraint but `NotNull`. An upload that did not arrive whole passes too: the
`File` constraint reports it, and a partial file is never scanned. Any other value is a violation of its type.

| Option | Default | Meaning |
|---|---|---|
| `context` | `upload` | Where the file comes from, given to the events: `manuscript`, `cover`, `attachment`. |
| `infectedMessage` | above | The message of an infected file. |
| `notScannedMessage` | above | The message of a file that could not be scanned. |
| `groups`, `payload` | | As with every constraint. |

The messages never name the signature: the person who sent the file learns that it was refused, not what the
antivirus found. The signature reaches the operator through the event and the quarantine record.

Put `#[CleanFile]` after the constraints that check the type and size, so that a file refused for those reasons is
not sent to the antivirus: Symfony validates the constraints of a property in their order, and a `GroupSequence` stops
at the first group with a violation.

### Check access before the scan

Since Symfony 8.2, an argument mapped by `#[MapUploadedFile]` that is the `subject` of an `#[IsGranted]` is validated
**before** the vote: its constraints, `#[CleanFile]` included, run for a visitor the vote would refuse. That visitor
makes the server send files to clamd, and reads the violations. Check the account first, then vote on the file:

```php
#[IsGranted('IS_AUTHENTICATED')]
#[IsGranted('COVER_UPLOAD', 'cover')]
public function upload(#[MapUploadedFile([new Assert\Image(maxSize: '10M'), new CleanFile(context: 'cover')])] UploadedFile $cover): Response
```

`phil/route-access-check` reports a route whose vote on a mapped argument has no check before it as a conflict.

## The `UploadScanner`

Outside a validated object, inject `Phil\UploadScanBundle\Application\UploadScanner`:

| Method | Scans |
|---|---|
| `inspectPath($path, $name, $context)` | A local file. A path that is not a readable file is an error result. |
| `inspectContents($contents, $name, $context)` | A string, through a temporary stream. |
| `inspectStream($stream, $name, $context)` | A whole stream, rewound first when it was read before; an infected file is quarantined only from a seekable stream. |

Each answers an `Inspection`:

| Member | Meaning |
|---|---|
| `result` | The `ScanResult`: clean, infected with its signature, or an error with its reason. |
| `decision` | The `Decision` of the policy: `Accept`, `Flag` or `Reject`. |
| `quarantineId` | Where the infected file was kept, or `null`. |
| `accepted()` | Whether the file is kept, flagged or not. |
| `flagged()` | Whether it is kept unscanned. |
| `infected()` | Whether the antivirus found a threat. |

`$name` is the name the sender gave the file: it reaches the events and the quarantine record, never a path.
