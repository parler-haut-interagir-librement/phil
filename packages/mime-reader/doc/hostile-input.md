# Hostile input

A mailbox open to the world receives messages written to break their reader. The reader holds to three rules: it never
throws on a message, it never does more work than its limits allow, and it never acts on what a message holds.

## The limits

| `Limits` | Default | What it bounds |
|---|---|---|
| `maxBytes` | 25 MiB | The octets of the message read; the rest is not read |
| `maxDepth` | 12 | The nesting of multiparts and held messages, the message itself counted |
| `maxParts` | 256 | The parts read in all, the message itself counted |
| `maxHeaders` | 500 | The header fields read in each part |
| `maxParameters` | 32 | The parameters read in one header field |

Each is at least 1; `InvalidLimits` says so otherwise. A part cut by a limit is marked `incomplete`: the message cut at
`maxBytes`, a multipart whose parts ran past `maxParts`, a container at `maxDepth` whose parts are left unread and kept
as its body. The parts read before the limit are kept.

## Bounded work

Every search for a boundary or an empty line stays inside its part, in windows that grow from 4 KiB to 1 MiB, so that a
boundary never closed does not send each part scanning to the end of the message. The parts of a message share its
octets rather than copying them, and bodies are decoded only when asked, in chunks of 64 KiB. Thousands of parts,
nesting far deeper than the limit and a megabyte of text each cost milliseconds, as the benchmarks measure.

## What is refused

- A media type that is not a type and a subtype of token characters takes the default of its place.
- A boundary longer than 200 characters or holding a control character leaves the multipart unsplit.
- A header line without a colon, or whose name is not printable, is skipped.
- A parameter section number past three digits is skipped.
- A character set unknown to both mbstring and iconv, a name holding a `/` that iconv would read as its options, or
  one that names a transfer encoding or entities rather than a character set, is
  read as UTF-8 with its invalid sequences replaced.

## What is never done

- Nothing in a message is run, included, unserialized or fetched: no HTML is rendered, no URL is followed, no
  `Content-Location` is resolved.
- A file name is never a path: directories, control characters and leading dots are removed.
- Every text the reader gives — decoded fields, display names, parameters, `text()` — is valid UTF-8.
- Addresses are not validated, and so not trusted: a `Mailbox` is what the sender wrote.
