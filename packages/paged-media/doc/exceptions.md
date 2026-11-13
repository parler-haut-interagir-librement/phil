# Exceptions

Every exception implements `Phil\PagedMedia\Exception\PagedMediaException`.

## The native writer

Each extends `InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidDocument` | In a PDF/A document, a standard font, a character the font lacks, or a CMYK image; drawing before any page; a font size not above zero; a page side outside 1 to 14 400 points; a colour component outside 0 to 255, or a colour not written `#rrggbb` or `#rgb` |
| `InvalidFont` | A font file that cannot be read, is not TrueType, has PostScript outlines, lacks a required table or a Unicode map, is truncated, declares no units per em, or whose licence forbids embedding |
| `InvalidImage` | An image file that cannot be read, is neither JPEG nor PNG, is corrupt or truncated, is an interlaced PNG, has an unknown colour type or row filter, or an indexed PNG without a valid palette |

Each message names what is wrong, for example `The licence of the font "Example-Regular" forbids embedding it in a
document.`

## The Gotenberg client

Each implements `Gotenberg\Exception\GotenbergException`, which extends `PagedMediaException`.

A request refused before it is sent extends `InvalidArgumentException`:

| Exception | Thrown when |
|---|---|
| `InvalidStage` | A stage's option Gotenberg would refuse: a page selection, an angle, a colour, a size or an opacity out of range, a missing option, two options that exclude each other, a file of the wrong type, a pair of profiles that do not go together |
| `InvalidPipeline` | A list of stages in an order Gotenberg refuses ([Stages](stages.md)) |
| `InvalidRequest` | No file, two different files of the same name, a file that cannot be read or written, a URL that is not absolute HTTP, a header value with a line break, conversion options that exclude each other |

An answer that is not a success extends the abstract `GotenbergFailure`, a `RuntimeException` that keeps the HTTP
`$status` (`0` when Gotenberg could not be reached), the request's `$trace`, and says by `isRetryable()` whether the
same request may succeed later:

| Exception | Status | Retryable |
|---|---|---|
| `RequestRefused` | 400, 405, 413, 415 and the other 4xx | no |
| `AccessDenied` | 401, 403 | no |
| `RouteUnavailable` | 404 | no |
| `ServiceUnavailable` | 429, 503, or no answer | yes |
| `ConversionFailed` | 409, 500 and the other 5xx | no |
| `UnexpectedResponse` | 200 with a body of another shape | no |

`CertificateUnavailable`, a `RuntimeException`, is thrown by a certificate source that cannot give the certificate: a
vault out of reach or a secret of another shape.

The message of a failure is Gotenberg's own, after the status: `Gotenberg answered 400: Stage 'select' failed on
'report.pdf': invalid page range: page 9 out of range [1, 3]`.
