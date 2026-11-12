# Exceptions

Every exception of the library implements `Phil\IpAnonymizer\Exception\IpAnonymizerException`, and extends
`InvalidArgumentException`. **None of their messages repeats an address**: an address is personal data, and exception
messages end up in logs.

| Exception | Thrown when |
|---|---|
| `InvalidIpAddress` | `IpAddress::parse()` is given text that is not an address, `fromBytes()` neither 4 nor 16 bytes. |
| `InvalidNetwork` | CIDR notation is malformed or has host bits set, a prefix length is outside its family, a table of labels lists a network twice or has an empty or overlong label. |
| `InvalidSalt` | A salt identifier or its length is out of bounds, a master key is not 32 bytes. |
| `InvalidOption` | A digest length is outside 16 to 64 bytes, a rotating period is shorter than a second, the clock reads an instant before the Unix epoch. |

`IpAddress::tryParse()` returns `null` instead of throwing: the form for an address read from a request or a log line.

The parameters that receive an address, a salt or a master key carry `#[SensitiveParameter]`, so that a stack trace
does not show them.
