# Exceptions

Every exception of the library implements `Exception\AccessibilityCheckException`, which extends `Throwable`: catch it
to catch them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidPolicy` | `InvalidArgumentException` | a limit below 1, a default colour that is not an opaque CSS colour, a token colour that cannot be read, two rules of one identifier |
| `LimitExceeded` | `RuntimeException` | a content larger, deeper, or of more elements or blocks than the policy allows |
| `InvalidContent` | `RuntimeException` | HTML that is not UTF-8 |

## Messages

| Factory | Message |
|---|---|
| `InvalidPolicy::limit($name, $value)` | `The maximum size must be at least 1; got 0.` |
| `InvalidPolicy::colour($name, $value)` | `The foreground must be an opaque CSS colour; got "var(--text)".` |
| `InvalidPolicy::duplicateRule($id)` | `Two rules have the identifier "image-alt"; an identifier names one rule.` |
| `LimitExceeded::size($bytes, $limit)` | `The content holds 6000000 bytes; at most 5242880 are checked.` |
| `LimitExceeded::depth($limit)` | `The content nests deeper than 256 levels.` |
| `LimitExceeded::count($limit)` | `The content holds more than 100000 elements or blocks.` |
| `InvalidContent::notUtf8()` | `The content is not valid UTF-8.` |

The constraint turns `LimitExceeded` and `InvalidContent` into a violation, the publication guard into a blocker, and
the command into the exit code 2.
