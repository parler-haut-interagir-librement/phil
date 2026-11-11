# MIME

`Mime\MimeParser::parse(string $raw): Mime\Part` reads an Internet message (RFC 5322) and its MIME structure
(RFC 2045, RFC 2046). It never throws: what it cannot read is kept as text.

| Read | How |
|---|---|
| Header fields | Unfolded and trimmed, names compared without case; a line without a colon, or a name with a space, is skipped |
| Encoded words (RFC 2047) | Decoded to UTF-8 by `Headers::decoded()`, on request |
| Media type | In lowercase, `text/plain` when missing or malformed (`message/rfc822` inside a digest), parameters by lowercase name, quoted values unescaped |
| Transfer encodings | `base64` (characters outside its alphabet ignored, an impossible length gives nothing) and `quoted-printable` undone |
| Multiparts | Split at their boundary lines, preamble and epilogue dropped; a missing closing line ends the multipart with the text |
| Held messages | `message/rfc822` and `message/global` read as the message they hold, in `Part::$message` |

A part that starts with an empty line has no header fields.

## Parts

| Member | Holds |
|---|---|
| `headers` | The `Mime\Headers`: `get()`, `all()`, `has()`, `decoded()`, `fields` |
| `type`, `parameters` | The media type and its parameters |
| `body` | The body, its transfer encoding undone, in its own character set |
| `parts` | The child parts of a multipart |
| `message` | The message a `message/rfc822` holds |
| `walk(bool $intoMessages = false)` | This part and every part under it, depth first |
| `find(string $type)` | The first part of a media type, without entering held messages |

## Limits

`Limits` bounds the work on one message, whatever it holds:

| Limit | Default | Beyond it |
|---|---|---|
| `maxBytes` | 1 MiB | Not read: a report puts what matters first, the original message last |
| `maxDepth` | 8 | A multipart is not split, a held message not read |
| `maxParts` | 64 | No more part is read |
| `maxHeaders` | 500 | No more field of a part is read |
| `maxText` | 64 KiB | The text a rule or the reading of a text bounce looks at is cut |

Each limit is at least 1, else `Exception\InvalidLimits`.
