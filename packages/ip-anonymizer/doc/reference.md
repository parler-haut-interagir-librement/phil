# Reference

All types are in `Phil\IpAnonymizer`.

## Addresses

| Type | Role |
|---|---|
| `IpAddress` | An IPv4 or IPv6 address as its bytes; `parse()`, `tryParse()`, `fromBytes()`, `equals()`, `toString()`. |
| `IpFamily` | `V4` or `V6`, with `bits()`. |
| `IpNetwork` | An address with its host bits clear and a prefix length; `containing()`, `parse()`, `contains()`, `equals()`, `toString()`. |
| `AddressScope` | The special-purpose scope of an address. |
| `ScopeClassifier` | `scopeOf()`: the `AddressScope` of an address. |

## Strategies

| Type | Role |
|---|---|
| `Truncation` | Clears the host bits past a prefix of each family; also an `IpTransformer`. |
| `KeyedHash` | The BLAKE2b digest of an address keyed by the current salt; `digest()` gives the `IpDigest`. |
| `IpDigest` | A digest's salt identifier and bytes; `equals()` in constant time, `toString()`. |
| `NetworkGeneralisation` | The network of an address in CIDR notation; `network()` gives the `IpNetwork`. |
| `ScopeGeneralisation` | The scope of an address as its label. |
| `LabelGeneralisation` | The label of a network that holds the address, else a fallback strategy. |
| `NetworkLabels` | A table of networks and labels, looked up by longest prefix. |
| `Pipeline` | Transformers in their order, then a strategy. |
| `PerFamily` | A strategy for IPv4 and another for IPv6. |

## Salts

| Type | Role |
|---|---|
| `Salt` | Secret bytes and an identifier; `of()`, `random()`. |
| `ForgottenSalt` | A random salt per period of a clock, shared through an `ExpiringSaltStore` and forgotten at the end of the period. The provider to reach for. |
| `FixedSalt` | Always the same salt. |
| `RotatingSalt` | A salt per period of a clock, derived from a master key; optional, for when nothing may be stored. |

## Contracts

In `Phil\IpAnonymizer\Contract`: `IpAnonymizer`, `IpTransformer`, `SaltProvider`, `ExpiringSaltStore`,
`NetworkLabeller`. See [Extension points](extension-points.md).

## Exceptions

In `Phil\IpAnonymizer\Exception`: `IpAnonymizerException`, `InvalidIpAddress`, `InvalidNetwork`, `InvalidSalt`,
`InvalidOption`. See [Exceptions](exceptions.md).
