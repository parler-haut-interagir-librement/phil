# Reference

| Type | Role |
|---|---|
| `EmailAddress` | An address a mail server can deliver to: part before the @, domain in Unicode and ASCII |
| `SyntaxPolicy` | The forms of RFC 5321 and RFC 6531 an application accepts |
| `SyntaxError` | The reason a text is not an address, with `describe()` |
| `EmailValidator` | The steps in order: syntax, top-level domain, domain rules, disposable domains, DNS, and a suggestion |
| `Validation` | What the validator found of one address |
| `Problem` | Why an address is refused |
| `TopLevelDomains` | The top-level domains the root zone delegates, bundled or the application's |
| `DomainRules` | Denied and allowed-only domains, with their subdomains |
| `DisposableDomains` | The domains of disposable mailboxes, bundled or the application's |
| `TypoSuggester` | The address meant when the domain is a slip from a well-known one |
| `MailDomain` | What the DNS says of a domain as a destination of mail |
| `Contract\MailDomainResolver` | The port to the DNS |
| `Dns\DnsGetRecordResolver` | The resolver through `dns_get_record()` |
| `Dns\StaticResolver` | The resolver from a map |
| `Data\DisposableDomainsImporter` | The import of a list of disposable domains into `data/` |
| `Data\TopLevelDomainsImporter` | The import of the IANA list of top-level domains into `data/` |
| `Validator\DeliverableEmailAddress` | The constraint of the Symfony Validator, with its options, messages and codes |
| `Validator\DeliverableEmailAddressValidator` | The constraint's validator, which runs `EmailValidator` |
| `Exception\EmailAddressException` | The interface of every exception of the library |
| `Exception\InvalidEmailAddress` | A text that is not an address, with its `SyntaxError` |
| `Exception\InvalidDomainList` | A list holding an entry that is not a domain, a release that cannot be recorded, or a file that cannot be written |
| `Internal\*` | The domain name and top-level label checks, the set of domains, the distance and the data directory: not part of the public API |
