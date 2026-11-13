# Exceptions

The bundle's exceptions implement `Phil\PagedMedia\Exception\PagedMediaException`, like the library's.

| Exception | Thrown when |
|---|---|
| `NotConfigured` (`LogicException`) | Gotenberg without `gotenberg.url`, a signature without `signing.certificate`, a timestamp without `signing.timestamp_url`, HTML without a `PrintEngine` |
| `UnknownTemplate` (`InvalidArgumentException`) | A template name no service is registered under; the message lists the names known |
| `StorageFailed` (`RuntimeException`) | A path that leaves the storage, a directory that cannot be made, a file or a job that cannot be written, a job that cannot be removed, a cache pool that fails, with its exception as the previous one |
| `InvalidTemplateParameters` (`InvalidArgumentException`) | A printed template's parameter missing or of the wrong kind; the message names the template, the path of the value and what is expected |
| `JobUnavailable` (`RuntimeException`) | No job kept under an identifier, a kept job `JobCodec` did not write, a job of another kind than its message |

The library's exceptions pass through `PagedMedia` as they are: `InvalidStage`, `InvalidPipeline` and `InvalidRequest`
before a request is sent, the `GotenbergFailure`s after, `CertificateUnavailable` from a vault. In a worker,
`PagedMediaHandler` wraps them for Messenger ([Messenger](messenger.md)).

A configuration Symfony refuses — a certificate without what its type needs, a timeout below zero — throws
`InvalidConfigurationException` when the container is built.
