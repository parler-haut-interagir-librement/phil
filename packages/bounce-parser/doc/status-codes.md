# Status codes

`StatusCode` is an enhanced mail system status code (RFC 3463), `class.subject.detail`.

| Member | Gives |
|---|---|
| `StatusCode::parse(string)` | The code written alone, spaces around it allowed, or `null` |
| `StatusCode::find(string)` | The first code in a text, or `null`; a code inside a longer run of digits and dots — a version, an IP address — is not one |
| `class`, `subject`, `detail` | The three numbers |
| `isSuccess()`, `isTransient()`, `isPermanent()` | Class 2, 4, 5 |
| `isAboutTheMessage()` | A full mailbox (`x.2.2`), a message too long (`x.2.3`, `x.3.4`), a refusal by policy (`x.7.*`) |
| `describe()` | The subject in a few English words: addressing, mailbox, mail system, network and routing, protocol, content, security or policy |
| `toString()`, `(string)` | `5.1.1` |

The class is 2, 4 or 5; the subject and the detail have one to three digits.
