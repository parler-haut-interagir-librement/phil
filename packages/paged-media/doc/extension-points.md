# Extension points

The library is plain PHP and declares no service. What an application chooses goes through arguments; three
interfaces let it bring what the library does not hold.

## `CertificateSource`

`Phil\PagedMedia\Gotenberg\Signing\CertificateSource` — the certificate a PDF is signed with, asked for at each
signature so that a renewed certificate is taken without a restart. See [Signing](signing.md).

| Method | Contract |
|---|---|
| `certificate(): Certificate` | The certificate and its key, as `Certificate::pkcs12()` or `Certificate::pem()` builds them; `CertificateUnavailable` when it cannot be read |

`FileCertificateSource`, `InlineCertificateSource` and `VaultCertificateSource` are shipped. An application with a
vault of its own implements the interface, and never keeps the key in a log or a message.

## `TimestampAuthority`

`Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority` — an RFC 3161 authority.

| Method | Contract |
|---|---|
| `url(): string` | The authority's URL, which Gotenberg calls itself |

`TimestampAuthorityUrl` is one known by its URL; an application that chooses the authority at run time implements the
interface.

## `Stage`

`Phil\PagedMedia\Gotenberg\Stage\Stage` — one operation on a PDF, in a `Pipeline` or alone. See [Stages](stages.md).

| Method | Contract |
|---|---|
| `operation(): Operation` | Which of Gotenberg's operations it is |
| `options(): array` | The options as Gotenberg names them, the ones left unset omitted |
| `files(): array` | The files the options name, uploaded beside the PDFs |

The operations are a closed enum, Gotenberg's own: a stage of the application's builds its options otherwise — from
its storage, say — for an operation the library knows.

## Fonts

The native writer takes a `StandardFont` or a `TrueTypeFont` and nothing else: what it writes, it must know how to
embed. `Phil\PagedMedia\Font\Font` — `width(string $text, float $size): float`, in points — is what both give the
application's own layout code to measure text with. See [Fonts](fonts.md).

## Arguments

| Point | How |
|---|---|
| Where Gotenberg is | The HTTP client, base URL, `BasicAuth` and timeout given to `Gotenberg` — see [Gotenberg](gotenberg.md) |
| Results delivered later | A `Webhook` in `RequestOptions`, and `WebhookEvent::fromJson()` on the application's endpoint |
| Embedded fonts | `TrueTypeFont` from the application's files, subset when written; their licences are the application's to respect |
| Printed templates | A `LabelSheet` of the application's stock, `TemplateFonts`, and the values already formatted in the reader's language; a template of its own draws on the `Document` a template returns — see [Printed templates](templates.md) |
| The page of an HTML and CSS engine | A `PageRule` of the application's size, margins, bleed and marks, given to the engine as a stylesheet — see [HTML and CSS engines](html-and-css.md) |
| Form fields | `TextField`, `CheckBox`, `RadioGroup` and `ChoiceField` built from the application's data, and `render(flattenForm: true)` for a copy that cannot be changed — see [Forms](forms.md) |
