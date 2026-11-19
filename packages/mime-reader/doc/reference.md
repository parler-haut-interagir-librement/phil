# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `MimeReader` | Reads a raw message, from a string or a stream, into its tree of parts |
| `Limits` | The octets, depth, parts, header fields and parameters read in one message |
| `Part` | A message or one of its parts: header fields, type, parameters, body decoded on demand, child parts, held message |
| `Headers` | The header fields of a part or a group of report fields, unfolded, with decoding, parameters and address lists |
| `HeaderValue` | A header value with its parameters, sections and character sets joined and decoded |
| `Mailbox` | One address of an address field, with its decoded display name |
| `Exception\MimeReaderException` | Every exception the package throws |
| `Exception\InvalidLimits` | A limit below 1 |
| `Exception\InvalidStream` | A value given as a stream that is not an open stream |

The classes of `Internal\` are the reader's own and may change in any version.
