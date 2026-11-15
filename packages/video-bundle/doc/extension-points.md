# Extension points

## Ports

| Port | Default | An application replaces it to |
|---|---|---|
| `Application\Contract\ViewingConsent` | `NoViewingConsent`: nobody agreed | Answer with its consent manager: `tracking.consent` |
| `Application\Contract\ViewingStore` | `InMemoryViewingStore` | Keep heat maps: `tracking.store: cache`, or its own store |

Both keep no state between requests: the memory store is emptied by `kernel.reset`.

## The event

`Application\Event\VideoProgressed` is dispatched for each viewing counted, carrying its `ViewingProgress`: the share
watched, the parts. It is how another bundle — a course — learns that a video was watched.

## Templates and controller

`@PhilVideo/components/Player.html.twig` is overridden as any bundle template. The facade, the tracks and the
transcript are recipes of the kit an application may restyle.

## Stability

The configuration, the ports, the event and the component's props are the public API, at 0.1: they may change in a
minor version until 1.0.
