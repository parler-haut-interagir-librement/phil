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

## Running the worker

Building a long book takes time. Give `WriteEpub` a transport of its own when other messages share the worker, so that
a book never holds back the messages a user is waiting for, such as an email:

```yaml
framework:
    messenger:
        transports:
            epub: '%env(EPUB_TRANSPORT_DSN)%'
        routing:
            Phil\EpubBundle\Application\Message\WriteEpub: epub
```

```sh
php bin/console messenger:consume epub --time-limit=3600
```

A worker checks `--time-limit` between messages and never stops a book under way: set it longer than the slowest book,
so that a worker does not restart after nearly every message.

To write more books at once, run several workers on the transport rather than `messenger:consume --concurrency`. Until
[symfony/symfony#68459](https://github.com/symfony/symfony/pull/68459) is merged, a handler longer than about fifteen
seconds holds the parent process of `--concurrency` until it ends, and no other message starts meanwhile: keep
`--concurrency` for transports whose handlers are short.
