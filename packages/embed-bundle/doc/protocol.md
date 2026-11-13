# Protocol

The page in the frame and the element that holds it speak by `postMessage`, in messages of this shape:

```json
{"protocol": "phil-embed", "version": 1, "type": "resize", "height": 640}
```

| Type | From | Holds |
|---|---|---|
| `ready` | the frame | nothing else |
| `resize` | the frame | `height`: a whole number of pixels, from 0 to 100,000 |
| `theme` | the element | `theme`: a lowercase letter, then up to 31 lowercase letters, digits or `-` |

A message is a plain object with exactly these keys; anything else — another protocol or version, an unknown type, a
key more or less, a value out of bounds — is ignored.

## The checks

| Side | Posts to | Reads |
|---|---|---|
| The frame | the token's origin, never `*` | messages whose source is its parent and whose origin is the token's; a theme only among the embed's |
| The element | the origin of the frame's address | messages whose source is its own frame and whose origin is the frame's address's |

The frame tells it is ready once it starts, then its height each time it changes, measured on its `<html>` element; the
element sends the theme when its `theme` attribute changes.

## The reference

`Domain\ValueObject\EmbedMessage` is the protocol's reference: `EmbedMessage::fromJson()` reads a message as JSON of
1,024 bytes at most, `toArray()` writes one. The JavaScript reads messages by the same rules, in
`assets/src/lib/message.js`, and both are tested against the same fixtures, `tests/Fixtures/messages.json`. As
JavaScript does not tell `1` from `1.0`, neither does the reference.
