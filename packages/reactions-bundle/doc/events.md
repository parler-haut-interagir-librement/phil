# Events

| Event | When | Properties |
|---|---|---|
| `ReactionAdded` | A member chose a reaction | `content`, `member`, `code`, `at` |
| `ReactionRemoved` | A member took back a reaction | `content`, `member`, `code`, `at` |

Changing a reaction is a `ReactionRemoved`, then a `ReactionAdded`. Nothing is dispatched when nothing changes. The
moderation of a content, its notifications and a member's reputation listen to these events or read the store: none of
them is part of the bundle.

## On the message bus

The events hold identifiers only, a reference, a member's identifier, a code and a moment. With `messenger: true`,
each is also sent on the default bus, and the application routes it to a transport and handles it there. Without a
handler or a route, a synchronous bus refuses the message: turn the option on once something consumes it.

## Erasure

When `phil/privacy-bundle` is there, `ReactionsPrivacy` joins the exports and the erasures of a person, the data
subject being the member's identifier. An export lists the member's reactions, private ones too; an erasure deletes
them and the counters go down. Neither dispatches an event.

When a content is deleted, the application calls `Reactions::eraseContent()`.
