# Exceptions

The bundle's exceptions implement `Phil\Epub\Exception\EpubException`, like the library's.

| Exception | Thrown when |
|---|---|
| `UnknownBookSource` (`InvalidArgumentException`) | A source name no service is registered under; the message lists the names known |
| `StorageFailed` (`RuntimeException`) | A path that leaves the directory, a file that cannot be written, a media collection the library lacks, a medium it refuses |
| `FontNotEmbeddable` (`InvalidArgumentException`) | A face whose licence forbids embedding or allows bitmaps only, a family the font directory holds no readable file of |

The library's `InvalidBook` and `WriteFailed` pass through `Epubs` as they are; `MediaAssets::cover()` throws
`InvalidBook` for a medium without alternative text. In a worker, `EpubHandler` wraps them for Messenger
([Messenger](messenger.md)).

A configuration Symfony refuses — an unknown storage type, an empty folder — throws `InvalidConfigurationException`
when the container is built.
