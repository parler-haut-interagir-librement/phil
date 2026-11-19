# Security and privacy

## The seed

The seed fixes the avatar, and anyone who sees the avatar sees something of the seed. It is a stable opaque identifier
of the application: a UUID, or a random value kept on the member's profile, which the member may draw again to change
their avatar.

It is never an email address nor a hash of one. An avatar service keyed on a hash of the address tells everyone who sees
the avatar which address it belongs to: a dictionary of addresses reverses the hash. `Seed` refuses anything with an
`@`, and anything other than 1 to 255 visible ASCII characters.

The choices of an avatar come from SHA-256 of the seed and a counter: they spread seeds over colors and shapes, and are
not a secret. An avatar does not reveal its seed, but do not use as a seed a value that must stay secret.

## Markup safe by construction

An avatar is written by the library alone. Styles — the package's and the application's — draw through `Canvas`, which
writes every element itself:

- numbers are written from floats, two decimals at most, anything not finite as `0`;
- colors are written from `Color`, which only `#rrggbb` reaches;
- text — initials, the label — is escaped for XML, quotes included, invalid UTF-8 replaced;
- the identifier of a clip is a hash written in hexadecimal.

No string of the caller reaches the document as markup: a name such as `"><script>` is drawn as its letters and named
as its text. The tests parse every avatar as XML and check that it holds no element the library does not write; the
fuzz target `initials` does the same on names nobody wrote.

## Bounds on every input

| Input | Bound |
|---|---|
| A seed | 1 to 255 visible ASCII characters, no `@` |
| A name | Any; read from its first kilobyte |
| A label | Valid UTF-8, 200 characters at most |
| A size | 16 to 1024 pixels |
| Initials | 1 to 3 letters |
| A palette | 1 to 64 backgrounds, each readable with one ink |
| A style's name | 1 to 32 lowercase letters, digits or `-` |

The work of an avatar is fixed by its style — a handful of elements —, not by its input.
