# Threat model

## What this project does and where untrusted input enters

PHIL is a monorepo of PHP 8.5 libraries and Symfony bundles (`packages/<name>/`, each published as `phil/<name>`). The
libraries are plain PHP; each bundle wires its library into a Symfony application. Many of them exist to take text,
files or addresses typed or uploaded by visitors of a website and make them safe to store, render or follow. That
input is untrusted: assume an attacker controls every byte of it, its size and its encoding.

The packages that receive untrusted input, and what each one promises:

| Package | Untrusted input | Promise |
|---|---|---|
| `svg-sanitizer` | SVG images | Output is safe to serve inline or as a file: no script, event handler, `foreignObject`, external reference, entity or document type; XML read without network; styles go through `css-sanitizer`; limits on size, depth, elements and attributes refuse an image whole. |
| `css-sanitizer` | Style sheets and `style` attributes | Output contains only allowed properties, functions, selectors and at-rules; `url()` only to allowed files; `@import`, `expression()`, `javascript:` and bindings refused; parse depth and length bounded. |
| `url` | Web addresses | Parsing follows the WHATWG URL standard; an address accepted as http or https is normalised and its host, scheme and kind are what the caller sees; `javascript:` and other schemes are never classified as web addresses. |
| `markup` | Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO, HTML, plain text | Compiled HTML at each level (`basic`, `forum`, `all`) contains nothing outside that level's allow list; no script, event handler or unsafe link; reading is linear in the input length. |
| `ujo` | UJO (Universal JSON Output) block documents as JSON | Strict JSON with size and depth limits; blocks validated; inline HTML purified; rendered HTML safe. |
| `typography` | HTML and text | Output HTML has the same elements and attributes as the input (only text nodes change); no markup is introduced; time linear in the input. |
| `upload-scan` | Uploaded files, antivirus daemon replies | A file is reported clean only on an explicit clean reply; size limits and timeouts hold; a malformed or hostile reply never yields "clean". |
| `email-address` | Email addresses | Only RFC 5321 / RFC 6531 mailboxes the application allows are accepted; no header injection through an accepted address; DNS lookups bounded. |
| `safe-http` | URLs to fetch, remote responses | No request reaches a private, loopback, link-local or reserved network, before or after a redirect or DNS change; time, size and redirect count bounded. |
| `spam-protection` | Form submissions, tokens | Submission tokens cannot be forged, replayed past their window or moved to another form; proof of work cannot be skipped. |
| `rate-limit` | Requests | Limits cannot be bypassed by spreading requests across servers when the guard accepts the configuration. |
| `idempotency` | Idempotency keys, request bodies | A key is scoped to its caller: one caller can never read or replay another caller's stored response; a different request under the same key is refused. |
| `passwords` | Passwords | Strength estimation in bounded time; only a 5-character hash prefix leaves the server. |
| `content-bundle` | Page bodies written by editors | A body is purified on every read; raw HTML is rendered only when its author holds the configured `raw_html_role`, and shown as text otherwise. |

The `*-bundle` packages of the libraries above (forms, validator constraints, Twig filters, controllers) carry the same
promises at the application boundary.

## Components that matter most / least

- Most: `svg-sanitizer`, `css-sanitizer`, `url`, `markup`, `ujo`, `safe-http`, `content-bundle`, then the rest of the
  table above.
- In scope but less exposed: packages that only read configuration or data written by the application's developers
  (enums, money, tax, geo, opening hours, sitemap…).
- Out of scope: `ux-css` and `ux-kit` front-end assets beyond the HTML they emit; development tooling (`vendor-bin/`,
  `bin/`, `phil-dev.php`); test fixtures and test applications under `packages/*/tests/`.

## Assumptions

- The packages run inside a Symfony application. Its configuration (`config/`, bundle options, allow lists, roles) and
  the data its developers write are trusted.
- Editors and administrators are authenticated by the application; holders of `raw_html_role` are trusted to publish
  raw HTML.
- The PHP runtime, its extensions and the operating system are up to date; their own defects are out of scope.

## How to exercise it

- `composer test` runs every suite; `vendor/bin/phpunit packages/<name>/tests` runs one package.
- `packages/*/tests/Fuzz/` holds fuzz targets for the fuzzer installed in `vendor-bin/fuzzer`.
- Each package documents its policy and limits in `packages/<name>/doc/`.

## How we rate severity

- Critical: a sanitizer output that runs script in a browser (stored XSS through `svg-sanitizer`, `css-sanitizer`,
  `markup`, `ujo` or `content-bundle` without the raw HTML role); `safe-http` reaching a private network; remote code
  execution.
- High: a URL classified as web when it is not, or with a host other than the one the browser would use; one caller
  reading another's idempotent response; a forged spam token; an upload reported clean without a clean reply.
- Medium: denial of service from a small input (super-linear time or memory past the documented limits); CSS-only
  data exfiltration or UI redressing through sanitized output.
- Low: information leaks in error messages; defects that need trusted configuration to be hostile.

## Anything to leave alone

- Behaviour that only trusted configuration or trusted roles can trigger, as stated in the assumptions.
- Missing protections that the documentation assigns to the application (authentication, CSRF on its own forms,
  HTTP headers).

## Security contact

See [`SECURITY.md`](SECURITY.md) at the root of the monorepo.
