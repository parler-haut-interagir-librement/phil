# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Configured by the application

| Type | What it sets |
|---|---|
| `Limits` | The octets, depth, parts, header fields and parameters read in one message |
| `Part::fieldGroups($groups, $fields)` | How many groups of report fields are read, and how many fields in each |
| `Headers::mailboxes($name, $limit)` | How many mailboxes are read from the fields of a name |

## Built by the application

`Part::create()` builds a part from values already decoded — headers, type, parameters, body, child parts, held
message — for a test, or for code that reads parts without a raw message. `Headers` and `HeaderValue` are built from
their values the same way.

## Relied on

- `read()` never throws on a message, and every text it gives is valid UTF-8.
- `MimeReader` keeps no state between calls: one instance serves every request and every worker.
- A part's tree and its values are immutable; decoding a body twice gives the same octets.
- `Part::read()` is the reader's own constructor, not an extension point.
