# Installation

```sh
composer require phil/mail-auth-check
```

The package needs PHP 8.5 or later, `ext-filter`, which reads the addresses of SPF records, and `ext-sodium`, which
reads the DKIM keys. `ext-intl`, suggested, converts an internationalised domain name such as `bücher.example` to its
ASCII form; without it, a profile that names one is refused with `InvalidDomain`, and the ASCII form
(`xn--bcher-kva.example`) works.

## A first check

```php
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$profile = new SenderProfile(
    sendingDomain: 'example.org',          // the domain of the From address
    returnPathDomain: 'bounce.example.org', // the envelope sender; the sending domain when left out
    dkimSelectors: ['s1'],                  // without "._domainkey"
    spfIncludes: ['_spf.provider.example'], // what the providers ask you to include
    minimumPolicy: DmarcPolicy::Quarantine, // "none" by default
);

$report = new MailAuthCheck()->check($profile);

$report->canSend();       // true when no finding failed
$report->failures();      // the findings that block sending
$report->warnings();      // the findings worth fixing
$report->forCheck('spf'); // the findings of one check: spf, dkim, dmarc or return-path
```

Each `Finding` carries the name of its check, a `Status` (`Pass`, `Warning` or `Fail`), a stable code such as
`spf.too-many-lookups`, which an application may translate or link to its help, and a message in English. The
[reference](reference.md) lists every code.

Check a sender profile when it is created and again on a schedule: DNS records change outside the application, and a
provider that adds an include can push an SPF record past its ten lookups.

## The resolver

`MailAuthCheck` asks the system's resolver by default, through `dns_get_record()`, which waits as long as the
resolver's own configuration allows (`timeout` and `attempts` of resolv.conf). A lookup that fails gives a failing
finding: a profile whose records could not be read is not reported as ready.

```php
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\MailAuthCheck;

// Preview the records a domain owner is about to publish.
$preview = new MailAuthCheck(new StaticTxtResolver([
    'example.org' => ['v=spf1 include:_spf.provider.example -all'],
    '_dmarc.example.org' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@example.org'],
]));
```
