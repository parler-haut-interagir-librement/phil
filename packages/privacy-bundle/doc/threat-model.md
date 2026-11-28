# Threat model

What the bundle protects, from whom, how, and what it leaves to the application. It holds personal data for a short
time (requests without an account, export archives), signs the visitor's consent, and writes the records a regulator
may ask for (proofs of consent, the register of incidents): each of them is an asset below.

## Assets

| Asset | Where it lives | What protects it |
|---|---|---|
| The address of a request without an account | `phil_privacy_guest_request` | Sealed with libsodium's `secretbox` under the key `phil_privacy.guest_request_store`; removed with the request. |
| The secret of a request's link, of an erasure's progress link | `phil_privacy_guest_request`, `phil_privacy_erasure_request` | 256 random bits, kept as their SHA-256, compared in constant time; the link works once and expires. |
| An export of someone's data | `account_deletion.export_directory` | A `0600` file of a `0700` directory, sealed with `secretbox` under the key `phil_privacy.export_archive`; refused from its expiry before it is opened, and removed by the next sweep. |
| The visitor's choice of cookies | The consent cookie | Signed with HMAC-SHA-256 under the key `phil_privacy.consent`; a value that does not verify reads as no choice. |
| The addresses and IPs that ask for links | The cache pool of `guest_requests.cache` | Never written: each limit is keyed on an HMAC-SHA-256 under its own key. |
| Personal values in logs | Every Monolog record | Replaced by `[redacted]` before any handler writes them ([Log redaction](log-redaction.md)). |
| Proofs of consent, the register of incidents | `phil_privacy_consent_proof`, `phil_privacy_incident` and its tables | Name no one beyond what the law asks; kept for their legal period; corrections kept as revisions. |

## The keys

Every key the bundle needs comes from the application secret (`kernel.secret`) by HKDF-SHA-256 (RFC 5869), through
`Domain\Secret\KeyDerivation`, with one label per usage:

| Label | Usage |
|---|---|
| `phil_privacy.guest_request_store` | Seals the address of a request without an account (`DbalGuestRequests`). |
| `phil_privacy.guest_requests.client` | Keys the per-client limit of requests without an account (`GuestRequests`). |
| `phil_privacy.guest_requests.address` | Keys the per-address limit of requests without an account (`GuestRequests`). |
| `phil_privacy.export_archive` | Seals an export archive (`EncryptedExportArchive`). |
| `phil_privacy.consent` | Signs the consent cookie (`ConsentToken`). |

**One label per usage keeps the keys unrelated.** A key that leaks from one usage, a signature the consent cookie shows
to every visitor, or a digest of the limiter's storage, gives nothing about the key that seals the addresses or the
archives. HKDF is the derivation made for this: the secret is its input keying material and the label its `info`,
where an HMAC keyed with a label and fed the secret uses the secret as a message and the label as a key.

**An empty secret is refused.** `KeyDerivation::key()` throws `InvalidPrivacyValue` rather than derive keys anyone can
compute; the services that need a key fail when they are built.

**Changing the secret changes every key.** The requests without an account, the export archives and the consent cookies
of the old secret read as absent or as no choice, and the limits start again; nothing else is sealed with these keys.
The bundle reads no previous secret: a rotation is a clean break, and these assets live hours or days, not years.

## Trust boundaries

1. **The browser to the bundle's pages.** The forms of requests without an account, the progress pages and the
   preferences read untrusted input: an address goes through `phil/email-address`, an identifier must match its shape,
   a secret is compared in constant time, every action is a `POST` with a CSRF token, and opening a link only shows a
   page, so that a mail scanner following it does nothing.
2. **The browser to the consent.** The cookie and `Sec-GPC` are untrusted. A cookie longer than
   `ConsentToken::MAX_LENGTH`, malformed, or signed under another key reads as no choice, which refuses every optional
   category; nothing a browser sends raises an error. The decoding is fuzzed (`composer fuzz consent-token`).
3. **The bundle to the database.** Trusted for integrity, not for the secrecy of what is sealed: a copy of
   `phil_privacy_guest_request` gives no address without the secret. A row that cannot be opened reads as absent.
4. **The bundle to the filesystem.** The export directory is trusted to keep its permissions; a file that does not
   open, or is shorter than its nonce, reads as absent.
5. **The modules to the bundle.** The exporters and erasers of the application are trusted code: the bundle bounds what
   they return in an export and records what each one reports, but believes their report.

## Threats and defences

| Threat | Defence |
|---|---|
| Learning whether an address is known | A request is answered the same way whether the address is known or not; what the link finds is decided once it is opened. |
| Filling someone's mailbox with links | The per-address limit drops the extra links silently. |
| A flood of requests | The per-client limit answers `429` with `Retry-After`, counted before anything is read. |
| Reusing or guessing a link | 256 random bits, a single use, a short life, constant-time comparison. |
| Granting a cookie category by writing the cookie | The HMAC of the value; a script or a subdomain without the secret cannot sign. |
| Reading addresses or exports from a copy of the database or of a backup | `secretbox` under keys derived from a secret the copy does not hold. |
| Running one export or erasure twice at once | A lock named after the request, read again once held, renewed before each module. |
| Personal data in logs, tickets and CI artefacts | The redaction processor, registered in every environment. |

## Risks left to the application

- **The secret itself.** Whoever holds `kernel.secret` can open the requests and archives and sign a consent cookie.
  Keep it out of the repository (Symfony's secrets vault or an environment variable), long and random.
- **Data the modules keep.** The bundle erases through the erasers the application writes; a table no eraser covers
  keeps its data.
- **The transport of the links.** A link to a request is sent by mail: whoever reads the mailbox can open it.
- **The rest of the page.** The bundle signs the consent; the application loads a third-party script only through
  `privacy_consent()` or `privacy_embed()`.
