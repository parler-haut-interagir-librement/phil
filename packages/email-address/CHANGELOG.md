# Changelog

All notable changes to `phil/email-address` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `EmailAddress::parse()`: the mailbox of RFC 5321 — dot-atoms, 64 octets before the @, 253 for the domain, 254 in
  all counted in ASCII —, the domain through IDNA (UTS #46, nontransitional, STD3, bidi and joiners checked) and kept
  in its Unicode and ASCII forms, `toString()`, `toAscii()`, `requiresSmtpUtf8()`, `isIpLiteral()` and `equals()`.
- `SyntaxPolicy`: non-ASCII local parts (RFC 6531, normalised to NFC), quoted local parts and IP literals, each refused
  by default and allowed by its `with` method.
- `SyntaxError`, the reason of each refusal with `describe()`, carried by `Exception\InvalidEmailAddress`.
- `DomainRules`: denied domains and allowed-only domains, each standing for its subdomains, a denied domain refused
  even when allowed.
- `DisposableDomains`: `bundled()`, `of()`, `merge()`, `contains()` and `count()`, subdomains included.
- `Data\DisposableDomainsImporter` and `bin/import-disposable-domains.php`: a list of disposable domains checked,
  written in ASCII without duplicates and sorted in `data/disposable-domains.php`, its source and release on its line
  of `data/VERSION`, each file written through Symfony's Filesystem and moved into place once written; the source list
  is dedicated to the public domain under CC0 1.0 (`data/LICENSE`, `NOTICE`).
- `TopLevelDomains`: `bundled()`, `of()`, `contains()`, `isEmpty()` and `count()`, letters or A-labels checked by IDNA.
- `Data\TopLevelDomainsImporter` and `bin/import-top-level-domains.php`: the IANA list of the root zone, its release
  taken from its `# Version` line, checked, written in lowercase without duplicates and sorted in
  `data/top-level-domains.php`, its source and release on its line of `data/VERSION` (`data/LICENSE`, `NOTICE`).
- `Contract\MailDomainResolver` and `MailDomain`: MX records, a null MX (RFC 7505), A or AAAA records only, none, or
  a failed lookup; `Dns\DnsGetRecordResolver`, which asks `dns_get_record()` with a final dot, and
  `Dns\StaticResolver`.
- `TypoSuggester`: the address meant when the domain is one or two slips — optimal string alignment distance, bounded,
  computed on the band within the limit after a native Levenshtein filter — from a well-known domain, or ends with a
  slip of `com`, `net` or `org` that is not itself delegated; its domains and slips replaceable.
- `EmailValidator` and `Validation`: syntax, top-level domain, domain rules, disposable domains, then the DNS, each
  step only when the ones before passed, an empty list of top-level domains checking nothing, a suggestion whatever
  the outcome, and `Problem` for each refusal.
- `Validator\DeliverableEmailAddress` and `Validator\DeliverableEmailAddressValidator`: a constraint of the Symfony
  Validator over `EmailValidator`, with its syntax options, the top-level domains and disposable domains on by
  default, denied and allowed-only domains, the DNS when asked, one message and one code per problem, and the
  parameters `{{ value }}`, `{{ domain }}`, `{{ reason }}` and `{{ suggestion }}`; built without a container, it reads
  the bundled lists.
- `Exception\EmailAddressException`, implemented by `InvalidEmailAddress` and `InvalidDomainList`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
