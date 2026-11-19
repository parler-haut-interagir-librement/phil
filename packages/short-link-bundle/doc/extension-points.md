# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | How |
|---|---|
| Something told of each visit | Implement `Application\Contract\VisitRecorder`; autoconfiguration tags it `phil_short_link.visit_recorder` |
| Visits counted by the analytics | The `analytics.site` option, with phil/analytics-bundle |
| Who a visit comes from | The `visitors.link_previews` and `visitors.robots` options, or an implementation of `Application\Contract\VisitorClassifier` aliased in its place |
| A check of a target before saving | Implement `Application\Contract\TargetCheck` and pass it to `ShortLinks`, or turn on `targets.check` |
| The allowance of creations | The `rate_limit` options, a rate limiter factory of the application in `rate_limit.limiter`, or an implementation of `Application\Contract\CreationThrottle` |
| Where the links are kept | Implement `Domain\Contract\ShortLinkRepository` and alias it |
| Creating links from code | `Application\ShortLinks`: `create()`, `update()`, `enable()`, `delete()`, `get()`, `url()` |
| Answering a request from code | `Application\ShortLinkResolver::resolve()` |
| Codes from code | `Application\Code\SqidCodec` and `Application\Code\CodeGenerator` |
| QR codes from code | `Application\ShortLinkQrCodes`: `svg()`, `png()` |
| The form in the application's own pages | `Infrastructure\Form\ShortLinkType`, on a `ShortLinkDraft` |
| The words of the administration | The messages of the `phil_short_link` domain |
| The pages of the administration | `admin.layout`, and the templates under `@PhilShortLink/admin/` overridden in `templates/bundles/PhilShortLinkBundle/` |
| The clock | The `clock` service: the bundle never reads the time itself |

## A classifier

`Application\Contract\VisitorClassifier` answers a `Domain\Enum\VisitorClass` from the `User-Agent` and the purpose
headers of a visit, the purpose headers joined by commas. The bundle registers `Application\Visitor\KnownVisitorClassifier`
and aliases the port to it; an application points the alias at its own service:

```php
namespace App\ShortLink;

use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VisitorClassifier::class)]
final readonly class KioskAwareClassifier implements VisitorClassifier
{
    public function __construct(private KnownVisitorClassifier $known)
    {
    }

    public function classify(?string $userAgent, ?string $purpose): VisitorClass
    {
        // The kiosks of the venue scan a link to check it before showing it.
        return str_contains($userAgent ?? '', 'VenueKiosk/') ? VisitorClass::Robot : $this->known->classify($userAgent, $purpose);
    }
}
```

A classifier reads the headers it is given and keeps none of them, holds no state between two calls, and bounds what it
reads: it runs on every visit, before the redirect.

## A recorder

A recorder receives identifiers only, after the response has gone:

```php
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VisitMessages implements VisitRecorder
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function record(ShortLinkVisit $visit): void
    {
        $this->bus->dispatch(new LinkVisited($visit->linkId));
    }
}
```
