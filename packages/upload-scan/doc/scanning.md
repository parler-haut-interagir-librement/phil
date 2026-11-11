# Scanning

## The port

```php
interface Scanner
{
    public function scanStream(mixed $stream): ScanResult;

    public function scanPath(string $path): ScanResult;
}
```

`scanStream()` reads an open stream whole and leaves it open. A seekable stream is rewound first: one read before the
scan — to sniff its type, hash it or measure it — is scanned from its start, never from where that read stopped, which
would send clamd nothing or only a tail and get a clean answer for an infected file. A stream that cannot be rewound,
such as a pipe or a socket, is scanned as it comes when nothing was read from it, and is an error result once read,
`The stream was read before the scan and cannot be rewound: give it to the scanner before reading it.`
`scanPath()` opens a local file and closes it. A value that is not an open stream is refused with `InvalidArgument`:
that is a mistake in the calling code. Everything else that keeps a file from being scanned — the engine unreachable or too slow, the file
unreadable or larger than the limit — is an error result, never an exception, so that the policy decides.

## The results

| Result | `status` | `signature` | `reason` |
|---|---|---|---|
| `ScanResult::clean()` | `clean` | `null` | `null` |
| `ScanResult::infected('Win.Test.EICAR_HDB-1')` | `infected` | the signature that matched | `null` |
| `ScanResult::error('clamd did not answer within 30 seconds.')` | `error` | `null` | why the file was not scanned |

`isClean()`, `isInfected()` and `isError()` ask the status. A signature and a reason are 1 to 255 bytes of printable
ASCII without spaces around them, so they can be logged and shown to an operator as they are; the clamd adapter cuts
what clamd sends to that shape.

## The policy

`ScanPolicy` turns a result into a `Decision`:

| Result | `OnScanError::Reject` (default) | `OnScanError::AcceptAndFlag` |
|---|---|---|
| clean | `Accept` | `Accept` |
| infected | `Reject` | `Reject` |
| error | `Reject` | `Flag` |

`Decision::keeps()` says whether the file is kept, flagged or not. Rejecting what could not be scanned is the safe
default: an attacker who can slow the antivirus down cannot slip a file past it. Accepting and flagging keeps uploads
working while the antivirus is down; the application then keeps a flagged file out of sight — not published, not
shared — until a later scan clears it.

```php
use Phil\UploadScan\Decision;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;

$decision = new ScanPolicy(OnScanError::AcceptAndFlag)->decide($scanner->scanPath($path));

match ($decision) {
    Decision::Accept => $library->publish($path),
    Decision::Flag => $library->holdForReview($path),
    Decision::Reject => $library->refuse($path),
};
```
