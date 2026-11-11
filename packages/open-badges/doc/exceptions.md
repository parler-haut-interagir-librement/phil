# Exceptions

Every exception of the library implements `Phil\OpenBadges\Exception\OpenBadgesException`: catch it to catch them
all.

| Exception | Extends | Raised when |
|---|---|---|
| `InvalidBadge` | `InvalidArgumentException` | A value given to build a credential does not hold together: an issuer, an achievement, a recipient, a key, an identifier, a period, an endorsement, a status entry or list, a learner record. |
| `InvalidCredential` | `RuntimeException` | A credential does not verify: malformed, signed with another algorithm or key, altered, not valid yet, expired, using JSON-LD outside the subset, or too costly to canonicalize; or its status cannot be read. |
| `InvalidImage` | `RuntimeException` | An image a credential is baked into or read from is malformed, too large, not a PNG or an SVG, holds two credentials, or holds something that is not a credential. |

Each carries its reason as its message, in English, for the logs; a verification page tells the person that the
credential does not verify, and may show the reason.
