# phil/email-address

**Accept the addresses a mail server can deliver to, refuse the rest, and catch the slip before it bounces.**

phil/email-address reads an email address as people type it and checks it in order: its syntax, as the mailbox of
RFC 5321 with an international domain and the forms of RFC 6531 the application allows; the domains the application
refuses or the only ones it accepts; the top-level domain, against the IANA list of the root zone; the domains of
disposable mailboxes, from a list imported as data; and, through a port, whether the DNS of its domain names a server that receives mail. Whatever the outcome, it suggests the address
the person probably meant when the domain is a slip away from a well-known one: `ada@gmial.com`, `ada@gmailcom`,
`ada@hotmail.con`.

It is for any PHP application that collects addresses: a sign-up form, a newsletter subscription, a contact form, an
identity provider, an import of a list. It answers "can mail reach this address?" before the first message is sent,
so that a typo does not become a bounce and a throwaway mailbox does not become an account.

It is a plain PHP library on `ext-intl`, `ext-ctype` and `ext-filter`, with two Symfony components: the Validator,
for its `DeliverableEmailAddress` constraint, and the Filesystem, to write its data. Domains go through IDNA (UTS #46) as browsers read them; nothing is filtered by a regular expression found on the web.

The design: `EmailAddress` is the value — the part before the @ as written, the domain in its Unicode and ASCII forms
— parsed under a `SyntaxPolicy`; `TopLevelDomains` holds the delegated top-level domains; `DomainRules` and
`DisposableDomains` hold domains and their subdomains;
`Contract\MailDomainResolver` is the port to the DNS, with `Dns\DnsGetRecordResolver` and `Dns\StaticResolver`;
`TypoSuggester` finds the address meant; `EmailValidator` runs the steps and returns a `Validation`;
`Validator\DeliverableEmailAddress` runs them inside the Symfony Validator.

## Status

| Part | State |
|---|---|
| Syntax of RFC 5321: dot-atoms, lengths of 64, 253 and 254 octets, labels of 63 | Implemented and tested |
| International domains (UTS #46, nontransitional, STD3, bidi and joiners checked) | Implemented and tested |
| Non-ASCII local parts (RFC 6531), quoted local parts and IP literals, each when allowed | Implemented and tested |
| Denied domains and allowed-only domains, with their subdomains | Implemented and tested |
| Disposable domains: the list, merging, the importer and its command | Implemented and tested |
| The bundled lists in `data/` | Not imported yet: both importers are ready, `data/VERSION` says `none` for each |
| MX, null MX (RFC 7505) and A/AAAA through a port, `dns_get_record()` adapter | Implemented and tested |
| Suggestions for slips of well-known domains and of `com`, `net` and `org` | Implemented and tested |
| Known top-level domains: the list, the IANA importer and its command | Implemented and tested |
| `DeliverableEmailAddress`, a constraint of the Symfony Validator | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the validator as a service. |
| [Syntax](doc/syntax.md) | What `EmailAddress::parse()` accepts and refuses, the policy, the forms of an address. |
| [Validation](doc/validation.md) | The steps of `EmailValidator`, the `Validation`, the problems. |
| [Domains](doc/domains.md) | Top-level domains, denied and allowed-only domains, disposable domains, the bundled lists and their import. |
| [DNS](doc/dns.md) | The resolver port, MX, null MX and A records, timeouts and caching. |
| [Symfony Validator](doc/symfony-validator.md) | The `DeliverableEmailAddress` constraint, its options, messages and codes. |
| [Suggestions](doc/suggestions.md) | How `TypoSuggester` finds the address meant, and how to tune it. |
| [Extension points](doc/extension-points.md) | The port, the arguments and the data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that validates addresses. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/email-address
```

```php
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\TopLevelDomains;

$validator = new EmailValidator(
    disposable: DisposableDomains::bundled(),
    resolver: new DnsGetRecordResolver(),
    topLevelDomains: TopLevelDomains::bundled(),
);

$validation = $validator->validate('ada@gmial.com');

$validation->isValid();                // true when gmial.com receives mail
$validation->suggestion?->toString();  // 'ada@gmail.com': ask "Did you mean…?"

$validator->validate('ada@localhost')->syntaxError?->describe(); // 'the domain has no dot'
```

In a Symfony form or entity:

```php
use Phil\EmailAddress\Validator\DeliverableEmailAddress;

#[DeliverableEmailAddress(checkMailServer: true)]
public string $email;
```

## Requirements

PHP 8.5 or later, `ext-intl`, `ext-ctype` and `ext-filter`; `symfony/validator` and `symfony/filesystem` 8.2 or
later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). The list of disposable domains in `data/` is dedicated to the public domain under CC0 1.0, and the
list of top-level domains is published by the Internet Assigned Numbers Authority; see [NOTICE](NOTICE).
