# Domains

## Top-level domains

`TopLevelDomains` holds the top-level domains the root zone delegates — `com`, `ca`, `xn--p1ai` —: a domain under any
other one receives no mail, so `ada@example.con` or `ada@shop.local` is refused before the DNS is asked.

| Method | Gives |
|---|---|
| `bundled()` | The IANA list imported in the library's `data/` |
| `of(iterable $labels)` | The application's own list, in ASCII: letters, or an `xn--` form IDNA accepts, in any case |
| `contains(EmailAddress\|string)` | Whether the last label of the address's domain, or of an ASCII domain, is listed |
| `isEmpty()`, `count()` | Whether the list holds no domain, and how many |

A label that cannot be a top-level domain throws `Exception\InvalidDomainList`. An empty list checks nothing:
`EmailValidator` skips its step. The names RFC 2606 and RFC 6761 reserve — `example`, `test`, `invalid`,
`localhost` — are not delegated: a test that writes addresses under them gives its own list through `of()`, or turns
the step off.

## Denied and allowed-only domains

`DomainRules::of(iterable $denied = [], iterable $allowedOnly = [])` holds the domains an application refuses, and
the only domains it accepts when it names some — an internal list kept to the organisation's domain, a staff sign-up.
`withDenied()` and `withAllowedOnly()` add to them. A domain stands for its subdomains: denying `example.com` denies
`mail.example.com`. A denied domain is refused even when it is also allowed.

`check(EmailAddress): ?Problem` gives `Problem::DeniedDomain`, `Problem::DomainNotAllowed` or `null`. Domains may be
written in any script and case; one that is not a domain name throws `Exception\InvalidDomainList`.

## Disposable domains

`DisposableDomains` holds the domains of disposable mailboxes, which receive mail for a few minutes and vanish, and
their subdomains.

| Method | Gives |
|---|---|
| `bundled()` | The list imported in the library's `data/` |
| `of(iterable $domains)` | The application's own list |
| `merge(DisposableDomains)` | Both lists |
| `contains(EmailAddress\|string)` | Whether the address's domain, or an ASCII domain, or one of its parents, is listed |
| `count()` | The number of domains listed |

`bundled()` reads a PHP file of a sorted array: OPcache keeps it in shared memory, and the lookup walks the labels of
the domain, at most one hash lookup a label.

## The bundled lists and their import

Each list is a PHP file in `data/`; `data/VERSION` holds one line per list — its file, its source and the release
imported — and each import rewrites its own line and keeps the others. Each file is written beside its place and moved
there, so that no reader sees half a file. The data is imported, never edited by hand, and imported again with each
version of the library.

### Disposable domains

`data/disposable-domains.php` is generated from `disposable_email_blocklist.conf` of
<https://github.com/disposable-email-domains/disposable-email-domains>, whose contributors dedicated it to the public
domain under CC0 1.0 Universal: it carries no condition, and its notice is kept in `data/LICENSE` and `NOTICE`.

To import a release:

```sh
php bin/import-disposable-domains.php path/to/disposable_email_blocklist.conf 2026-11-08
```

`Data\DisposableDomainsImporter` reads every line — one domain a line, `#` starting a comment — and checks each as a
domain name before anything is written: a list with a bad line changes nothing and names the line. The domains are
written in ASCII, without duplicates and sorted, so that two imports of one release give the same file. The release,
the commit or date given, holds no space. Check that the upstream `LICENSE.txt` still says CC0 with each import. A
domain the application wants to add or
remove goes through `DisposableDomains::of()` and `merge()`, or `DomainRules`.

### Top-level domains

`data/top-level-domains.php` is generated from <https://data.iana.org/TLD/tlds-alpha-by-domain.txt>, the list of the
root zone that the Internet Assigned Numbers Authority publishes; `NOTICE` and `data/LICENSE` name it.

To import it:

```sh
php bin/import-top-level-domains.php path/to/tlds-alpha-by-domain.txt
```

`Data\TopLevelDomainsImporter` takes the release from the list's first line, `# Version 2026111000, Last Updated …`,
and refuses a list without it. It checks every other line as a top-level label — letters, or an A-label whose Unicode
form IDNA accepts — before anything is written, and writes them in lowercase, without duplicates and sorted. A domain
the application wants to accept beyond the list, an internal one, goes through `TopLevelDomains::of()`.
