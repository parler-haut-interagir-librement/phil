# Exceptions

Every exception of the library implements `Exception\PasswordsException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidPolicy` | `InvalidArgumentException` | A `PasswordPolicy` or `PassphrasePolicy` out of bounds: a length or a number of words, no character set, a separator too long or with a letter. |
| `InvalidWordlist` | `InvalidArgumentException` | A wordlist of fewer than 256 words, or with a word empty, too long, repeated or with whitespace; an unknown bundled language. |
| `InvalidDictionary` | `InvalidArgumentException` | A dictionary without a name, an entry empty or longer than 64 characters, an unknown bundled dictionary. |
| `BreachCheckUnavailable` | `RuntimeException` | A breach source that cannot answer: network, status, timeout, size, malformed answer. `BreachChecker` catches it. |

The estimator and the generators throw nothing else: any string is a password the estimator reads.
