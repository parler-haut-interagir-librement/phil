# Messenger

`PagedMediaQueue` does in a worker what `PagedMedia` does at once, then keeps the file and announces it. Each call
keeps a job in the job store and dispatches a message that carries only the job's identifier: the documents, the
template's parameters, the options and the stages never travel in the queue, so a message is short and holds no
personal data.

| Call | Job kept | Message dispatched |
|---|---|---|
| `render($template, $parameters, $path, $pipeline, $sign, $timestamp, $filename)` | `RenderJob` | `RenderPdf($job)` |
| `run($files, $pipeline, $path, $sign, $timestamp)` | `PipelineJob` | `RunPipeline($job)` |
| `convert($documents, $path, $options, $pipeline, $sign, $timestamp)` | `ConversionJob` | `ConvertDocuments($job)` |

Each call returns the job's identifier, which the `PdfStored` event gives back. The print is announced, by
`PrintStarting` or `UnnamedPrintStarting`, when it is queued; the worker makes it with `PagedMedia::alreadyAnnounced()`,
which announces nothing more.

```php
$job = $queue->render('receipt', ['order' => 'R-42'], 'receipts/R-42.pdf', new Pipeline(new Remediate(Profile::PdfA3b)), sign: true);
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\PagedMediaBundle\Application\Message\RenderPdf: async
            Phil\PagedMediaBundle\Application\Message\RunPipeline: async
            Phil\PagedMediaBundle\Application\Message\ConvertDocuments: async
```

`sign` and `timestamp` end the list with the configuration's signature and document timestamp in the worker, so no key
is written into the job: prefer them to a `Sign` stage of your own, whose certificate and password the job would hold.

## The job store

`JobStore` is the claim check: `keep()` stores a job under an opaque identifier, `find()` gives it back to the worker,
and `remove()` forgets it once its file is kept. `DirectoryJobStore` writes each job as a JSON file of its own under
`jobs.directory`, named after 32 random hexadecimal digits; the directory and the files are readable by their owner
only, since a job holds the documents and the parameters of a file to come.

`JobCodec` writes a job as JSON and reads it back; nothing is unserialized, and a JSON it did not write is refused.

| Part of a job | Kept as |
|---|---|
| A file read from a path | Its name and its path, which the worker must be able to read |
| A file held in memory | Its name and its bytes in base64 |
| A download | Its URL and its headers |
| A stage | Its operation, its options as Gotenberg names them, and the files they name; read back as a `StoredStage` |
| The LibreOffice options | The options set, an enumeration by its value |
| The template's parameters | As they are: scalars and arrays |

Workers on several hosts share a store: `jobs.cache_pool` names a PSR-6 cache pool — Redis, a database, a filesystem
pool — and `CacheJobStore` keeps each job there under the same kind of identifier, for `jobs.lifetime` seconds or the
pool's default lifetime. Give it a pool of its own, whose lifetime outlasts the longest a message may wait and be
retried.

```yaml
framework:
    cache:
        pools:
            paged_media.jobs:
                adapter: cache.adapter.redis
                default_lifetime: 604800

phil_paged_media:
    jobs:
        cache_pool: paged_media.jobs
```

Alias the port to a service of your own — a database table, a Flysystem storage — to keep the jobs elsewhere;
`JobCodec` gives it the text to keep.

## Messenger's claim check

The job store keeps the messages short by design. Messenger 8.2's own claim check, the `claim_check` option of a
transport, moves any encoded message larger than its `max_size` into a cache pool and sends a reference instead; set it
on the transport of the bundle's messages as a guard — none of them should ever reach it — and give it the same kind of
dedicated pool, kept for the whole life of a message:

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                claim_check:
                    cache_pool: messenger.claims
                    max_size: 4096
```

With a signing serializer on the transport, Messenger signs the claimed envelope, not only its reference.

A job stays in the store until its file is kept, or until its message fails for good: a message retried finds it
there. A failure Messenger will not retry — an unrecoverable one, or the last retry spent — makes `FailedJobPurger`
forget the job, in a worker (`WorkerMessageFailedEvent`) and on the `sync://` transport (`SyncMessageFailedEvent`)
alike, so that the documents and parameters it holds do not stay behind. It listens after Messenger has decided on the
retry, and ignores every other message. A job the store cannot remove is left, and the worker goes on.

A message sent to a failure transport and retried from there with `messenger:failed:retry` needs its job: set
`jobs.remove_failed: false` to keep it, and remove it with `JobStore::remove()` once the failed message is dealt with,
or clear old files from the directory.

## The storage and the event

The file is kept by `PdfStorage` under the job's path, relative to the storage: `DirectoryStorage` writes it under
`storage.directory` and refuses a path that leaves it. Alias the port to a service of your own — a Flysystem storage, a
media library — to keep it elsewhere.

Once kept, a `PdfStored` event gives the job's identifier, the path, the file name, the media type, and the remediation
counts:

```php
#[AsEventListener]
public function __invoke(PdfStored $stored): void
{
    if (0 !== $stored->residual) { /* the PDF does not conform yet */ }
}
```

## What is retried

| Failure | Becomes |
|---|---|
| Gotenberg out of reach, busy (429) or timed out (503); a vault out of reach; a storage that cannot write or a job that cannot be removed | `RecoverableMessageHandlingException`: Messenger retries it, and the job waits |
| A job the store cannot give — none under the identifier, one it cannot read, or one of another kind than the message | `UnrecoverableMessageHandlingException`: never retried |
| Anything else the library or the bundle throws: a request Gotenberg refuses, an unknown template, a stage built wrong, a file whose path is gone | `UnrecoverableMessageHandlingException`: never retried |
