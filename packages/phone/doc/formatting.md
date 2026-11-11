# Formatting

| `PhoneFormat` | `+33 6 12 34 56 78` | `+1 514 555 0123 x12` |
|---|---|---|
| `E164` | `+33612345678` | `+15145550123` |
| `International` | `+33 6 12 34 56 78` | `+1 514-555-0123 ext. 12` |
| `National` | `06 12 34 56 78` | `(514) 555-0123 ext. 12` |
| `Rfc3966` | `tel:+33-6-12-34-56-78` | `tel:+1-514-555-0123;ext=12` |

Groups follow the plan of the country code's main country; the national form adds the national prefix as that
country writes it — `0 `, `(0)`, `8 (800)`. An extension is written as its country writes it, ` x12` in the United
Kingdom, ` ext. 12` by default. A number no format matches is written as its digits.

## Storing a number

`(string) $number` is E.164 with the extension as RFC 3966 writes it, `+15145550123;ext=12`, and `parse()` reads it
back to an equal number: the form to store, compare and send to services. `phil/phone-bundle` maps it to the column
type `phil_phone`.
