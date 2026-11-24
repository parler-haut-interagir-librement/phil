# Changelog

All notable changes to `phil/mail-auth-check` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- `SenderProfile`: the sending domain, the return path, the DKIM selectors, the SPF includes and the minimum DMARC
  policy a sender expects, its names in lowercase ASCII, internationalised names converted by ext-intl.
- `MailAuthCheck`, `Report`, `Finding` and `Status`: the checks of a profile run in order, their findings gathered, and
  the profile allowed to send when none failed.
- The `TxtResolver` port, `DnsGetRecordResolver` on the system's resolver and `StaticTxtResolver` from a map; each name
  asked once per check.
- `SpfParser`, `SpfAnalyser` and `SpfCheck`: SPF records read within bounds, includes and redirects followed, DNS
  lookups counted against the limit of ten, loops, broken includes and macros reported, the expected includes and the
  `all` result checked.
- `DkimKeyParser` and `DkimCheck`: the key of each expected selector read, revoked and testing keys reported, RSA keys
  measured (at least 1024 bits, a warning under 2048) and Ed25519 keys accepted.
- `DmarcParser`, `DmarcTreeWalk` and `DmarcCheck`: the DMARC record found by the DNS tree walk of RFC 9989, the
  organisational domain without the Public Suffix List, the policy compared with the profile's, reports and testing
  reported, and the return path's alignment checked.
- `ReturnPathCheck`: the SPF record of a return path that differs from the sending domain, with the expected includes.
- The `Check` port, for an application's own checks.
- A fuzz target for the SPF, DKIM and DMARC parsers and the SPF walk.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
