# phil/mail-auth-check

**Know before you send whether receivers will trust your mail: the SPF, DKIM and DMARC records of a sender profile,
checked against what it expects.**

phil/mail-auth-check is a plain PHP library. An application describes each sender as a **`SenderProfile`**: the domain
of its `From` address, the domain of its return path, the DKIM selectors its providers sign with, the SPF includes of
those providers, and the weakest DMARC policy it accepts. **`MailAuthCheck`** reads the DNS of that profile and returns
a **`Report`** of findings — pass, warning or fail, each with a stable code and a message a domain owner can act on.
The profile **can send** when no finding failed.

- **SPF** (RFC 7208): one record, readable, its includes followed and its **DNS lookups counted** against the limit of
  ten, loops reported, the expected providers included, and an ending that is not `+all`.
- **DKIM**: a key at `selector._domainkey` for each expected selector, not revoked, RSA of at least 1024 bits (a
  warning under 2048) or Ed25519.
- **DMARC** (RFC 9989): the record of the domain or, by the **DNS tree walk**, of a parent; a policy at least as strict
  as the profile expects; reports asked for; the return path aligned with the sending domain.
- **Return path**: its own SPF record authorises the expected providers.

The DNS is read through a **`TxtResolver`**: `DnsGetRecordResolver` asks the system's resolver, `StaticTxtResolver`
answers from a map, for tests or for records not yet published. Each name is asked once per check. The parsers bound
what they read — length, terms, tags, the depth of the SPF walk — so hostile records cost little.

## Status

| Part | State |
|---|---|
| `SenderProfile`, `MailAuthCheck`, `Report`, `Finding`, `Status`: profiles checked, findings gathered | Implemented and tested |
| `TxtResolver`, `DnsGetRecordResolver`, `StaticTxtResolver`: the DNS read through a port | Implemented and tested |
| `SpfParser`, `SpfAnalyser`, `SpfCheck`, `ReturnPathCheck`: SPF read, walked and counted | Implemented and tested |
| `DkimKeyParser`, `DkimCheck`: DKIM keys read and measured | Implemented and tested |
| `DmarcParser`, `DmarcTreeWalk`, `DmarcCheck`: DMARC found by the tree walk, policy and alignment | Implemented and tested |
| The `Check` port, for an application's own checks | Implemented and tested |
| A Symfony bundle: profiles stored, checks on a schedule and on demand | Not written |
| MTA-STS, TLS-RPT and BIMI checks; reading DMARC reports | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and a first check. |
| [Extension points](doc/extension-points.md) | The resolver and the checks an application adds, and when they are stable. |
| [Testing](doc/testing.md) | The tests, the static resolver in an application's tests, and the fuzz target. |
| [Reference](doc/reference.md) | Every public type and every finding code, with its role. |

## Quick start

```sh
composer require phil/mail-auth-check
```

```php
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$profile = new SenderProfile(
    sendingDomain: 'example.org',
    returnPathDomain: 'bounce.example.org',
    dkimSelectors: ['s1'],
    spfIncludes: ['_spf.provider.example'],
    minimumPolicy: DmarcPolicy::Quarantine,
);

$report = new MailAuthCheck()->check($profile);

if (!$report->canSend()) {
    foreach ($report->failures() as $finding) {
        echo $finding->code, ': ', $finding->message, "\n";
    }
}
```

## Requirements

PHP 8.5 or later, with `ext-filter` and `ext-sodium`. `ext-intl` converts internationalised domain names; without it,
give their ASCII form (`xn--`).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
