# Rescans

A file accepted and flagged while the antivirus was down is scanned again once it is back; every file can be scanned
again after a signature update. The bundle does it on a Messenger worker, with a message that carries identifiers
only.

## The message

```php
use Phil\UploadScanBundle\Application\Message\RescanFile;

$bus->dispatch(new RescanFile('manuscripts', $manuscript->id()));
```

`RescanFile` holds the name of a `RescanTarget` and the identifier of the file, each 1 to 255 bytes: never the file,
never anything about the person who sent it. The worker reads the file itself, through the target: the message is a
claim check, and the file stays where the application keeps it. Route it to an asynchronous transport:

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    rescan:
        transport: async
```

The same route may be given in the configuration of Messenger instead, as for any message.

## Scanning after the upload

A large file, or a site that must answer fast, may keep the upload first and scan it on the worker: the application
stores the file out of sight, marks its record pending, and dispatches `RescanFile` for it. Until `resolve()` clears
it, a pending file is never served to anyone but the person who sent it — or to nobody — exactly as a flagged one.

## The target

The application tells the bundle where its files are, and what a new inspection means, with a `RescanTarget`:

```php
use Phil\UploadScanBundle\Application\Contract\RescanTarget;
use Phil\UploadScanBundle\Application\Inspection;

final readonly class ManuscriptFiles implements RescanTarget
{
    public function name(): string
    {
        return 'manuscripts';
    }

    public function open(string $fileId): mixed
    {
        $manuscript = $this->manuscripts->find($fileId);

        return null === $manuscript ? null : $this->storage->readStream($manuscript->path());
    }

    public function resolve(string $fileId, Inspection $inspection): void
    {
        match (true) {
            $inspection->infected() => $this->manuscripts->takeDown($fileId),
            $inspection->flagged() => $this->bus->dispatch(new RescanFile('manuscripts', $fileId), [new DelayStamp(600_000)]),
            default => $this->manuscripts->clear($fileId),
        };
    }
}
```

A service implementing `RescanTarget` is tagged `phil_upload_scan.rescan_target` by autoconfiguration. The handler,
`RescanFileHandler`, is registered when Messenger is installed:

1. it finds the target by name — an unknown name throws `UnknownRescanTarget`;
2. it opens the file — `null`, a file that no longer exists, ends the message;
3. it inspects the file under the context `rescan.<target>`, so an infected file is quarantined and `FileInfected`
   dispatched as for an upload;
4. it closes the stream and hands the inspection to `resolve()`;
5. it dispatches `FileRescanned`, with the name of the target, the identifier of the file and the inspection.

A failure of `resolve()` stops the message before `FileRescanned`, so Messenger retries it as it retries any message.

## `FileRescanned`

A package that keeps files for the application listens to `FileRescanned` to follow the rescans of its target without
implementing what the target decides:

| Member | Value |
|---|---|
| `target` | The name of the target. |
| `fileId` | The identifier of the file. |
| `inspection` | The new `Inspection`. |
| `cleared()` | Whether the file was scanned and found clean: a flag set while the scanner was down can be lifted. |

```php
use Phil\UploadScanBundle\Application\Event\FileRescanned;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PublishClearedCovers
{
    public function __invoke(FileRescanned $event): void
    {
        if ('covers' === $event->target && $event->cleared()) {
            $this->covers->publish($event->fileId);
        }
    }
}
```

The handler keeps nothing between two messages, so a worker handles any number of them.
