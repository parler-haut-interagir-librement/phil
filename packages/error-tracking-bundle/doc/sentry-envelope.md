# The Sentry envelope

The endpoint of the browsers reads its own small report, not the envelope format of the Sentry SDKs. This is a
choice, kept for these reasons:

- **The SDKs send more than this bundle keeps.** An envelope carries breadcrumbs, the user, the request with its
  cookies and body when the SDK is so configured, the arguments of frames, attachments and sessions. Receiving it means
  parsing and then discarding most of it, and trusting a client's configuration for what reaches the server first.
- **The format is broad and moving.** An envelope is a sequence of typed items — events, transactions, sessions,
  attachments, client reports — with headers per item and compression. Reading it faithfully is a project of its own,
  and a parser exposed to the Internet is code to fuzz and maintain for each item type.
- **The key model differs.** A Sentry DSN names a project and a public key; this bundle checks an origin and an
  optional token, and keeps one application's errors in its own database.
- **The server side needs no SDK.** PHP errors are captured by the bundle's own listeners and handler; only the
  browser needed a client, and its controller is a few dozen lines.

An application moving from a Sentry SDK replaces it on the server with the bundle's [capture points](capture.md), and
in the browser with the Stimulus controller: [Migrating from sentry-symfony](migrating-from-sentry.md) goes through it
step by step. The capture points are the interface; a reader of the envelope, if one is ever needed, would be an
adapter named for that format beside them, never the core: it would turn each event item into an `ErrorEvent` through
`EventFactory::fromMessage()` and hand it to `SafeCapture`, so that the guard and the scrubber still run before
anything is written.
