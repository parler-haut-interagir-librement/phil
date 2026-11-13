# Messenger

```php
$bus->dispatch(new WriteEpub('story', '42', 'stories/42.epub'));
```

`WriteEpub` carries the name of a book source, an identifier and the path to keep the book under: no text, no personal
data, nothing heavy. The worker builds the book again from the application's records with the source, writes it and
keeps it, then dispatches `EpubStored` — the source, the identifier, the path, and where the storage keeps it — when an
event dispatcher is in the container.

| Failure | What Messenger does |
|---|---|
| `StorageFailed`, the library's `WriteFailed` | Retries it: a full disk, a storage out of reach may pass (`RecoverableMessageHandlingException`) |
| Any other exception of `phil/epub` or of the bundle — an unknown source, a book the library refuses, a font that cannot be embedded | Does not retry it (`UnrecoverableMessageHandlingException`) |

Route the message to an asynchronous transport like any other:

```yaml
framework:
    messenger:
        routing:
            Phil\EpubBundle\Application\Message\WriteEpub: async
```
