# Parsing

`PhoneNumber::parse($input, $region = null)` reads a number as people type it and keeps its country code, its
national number and its extension. `$region` is the ISO 3166-1 alpha-2 code of the country a number without its
country code is written in.

| Input | Region | Number |
|---|---|---|
| `+1 514-555-0123` | — | `+15145550123` |
| `(514) 555-0123` | `CA` | `+15145550123` |
| `1 800 555 0199` | `CA` | `+18005550199`: the national prefix `1` taken away |
| `06 12 34 56 78` | `FR` | `+33612345678` |
| `+33 (0)6 12 34 56 78` | — | `+33612345678` |
| `011 33 6 12 34 56 78` | `US` | `+33612345678`: dialled from North America |
| `+39 06 6982 1234` | — | `+390669821234`: Italy keeps its leading zero |
| `011 15 2345-6789` | `AR` | `+5491123456789`: Argentina's prefix rewritten as its plan says |
| `tel:+1-514-555-0123;ext=12` | — | `+15145550123`, extension `12` |

**What is forgiven**: spaces, non-breaking spaces, dots, dashes, slashes and parentheses; a `+` only at the start.
**What is read as an extension**, after the number: `;ext=`, `ext`, `ext.`, `extn`, `extension`, `x`, `#`, `poste`,
`anexo`, `int`, followed by 1 to 7 digits.

## Refusals

`parse()` throws `InvalidPhoneNumber` for:

- letters, or a `+` elsewhere than at the start: `1-800-FLOWERS`;
- an input of more than 100 characters, `PhoneNumber::MAX_INPUT`, refused before any pattern runs on it, so that
  reading a field costs the same whatever is sent;
- more than 50 characters once the extension is set aside, or a national number of fewer than 2 or more than 17 digits;
- a number without its country code and without a region;
- a country code no plan has;
- a region with no plan.

A number `parse()` reads is not always one that exists: `isValid()` tells, as
[Validation and types](validation.md) explains.
