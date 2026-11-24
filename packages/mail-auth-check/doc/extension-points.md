# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Read the DNS another way | Implement `Contract\TxtResolver`, and pass it to `MailAuthCheck` |
| Add a check | Implement `Contract\Check`, and pass the checks to `MailAuthCheck` |
| Run fewer checks, or reorder them | Pass the list you want; `MailAuthCheck::defaultChecks()` gives the four of the package |
| Use one part alone | Call `SpfParser`, `SpfAnalyser`, `DkimKeyParser`, `DmarcParser` or `DmarcTreeWalk` directly |

## The resolver

```php
interface TxtResolver
{
    /** @return list<string> */
    public function txt(string $asciiName): array;
}
```

`txt()` receives a name in lowercase ASCII without a final dot — `example.org`, `s1._domainkey.example.org`,
`_dmarc.example.org` — and returns one text per TXT record, its character strings joined without separator. A name
without records, or that does not exist, gives an empty list; a resolver that could not answer throws
`DnsLookupFailed`, which the checks turn into a failing finding. A resolver that follows CNAME records answers for a
DKIM key delegated to a provider.

An application's resolver may cache answers for their TTL, ask a DNS-over-HTTPS service, or answer from records it
already holds. `MailAuthCheck` wraps it so that each name is asked once per check; it keeps nothing between checks.

## A check of your own

```php
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

final readonly class MtaStsCheck implements Check
{
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        try {
            $records = $dns->txt('_mta-sts.'.$profile->sendingDomain);
        } catch (DnsLookupFailed) {
            return [Finding::fail('mta-sts', 'mta-sts.lookup-failed', 'The MTA-STS record could not be read.')];
        }

        return [] === $records
            ? [Finding::warning('mta-sts', 'mta-sts.missing', 'No MTA-STS record is published.')]
            : [Finding::pass('mta-sts', 'mta-sts.present', 'An MTA-STS record is published.')];
    }
}

$checker = new MailAuthCheck(checks: [...MailAuthCheck::defaultChecks(), new MtaStsCheck()]);
```

A check returns at least one finding, so that the report shows it ran, and turns what the DNS answers — a failed
lookup included — into findings rather than exceptions. It keeps no state between calls: the resolver it receives
serves one check of one profile.
