# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\ShortLinkBundle\PhilShortLinkBundle` | The bundle: its configuration `phil_short_link`, its services and the Doctrine mapping. |
| `Application\Code\SqidCodec` | Identifiers in the Sqids format: encode, decode, canonical check, blocklist. |
| `Application\Code\CodeGenerator` | A random number up to a maximum, written by the codec. |
| `Application\Code\DefaultBlocklist` | The default list of rude words in English and French. |
| `Application\ShortLinks` | Creates, changes, enables, disables and removes the links, each save through the same checks. |
| `Application\ShortLinkDraft` | A link as a form fills it in. |
| `Application\ShortLinkResolver` | The answer to a request on a short link domain, on the resolver of phil/redirects-bundle. |
| `Application\Resolution` | A redirect, a `410 Gone` or a `404`, with the visit a redirect counted. |
| `Application\ShortLinkVisit` | A visit counted: the link's identifier, its code, the host, its locale, the class of its visitor. |
| `Application\Contract\VisitorClassifier` | Tells who a visit comes from, from its `User-Agent` and purpose headers. |
| `Application\Visitor\KnownVisitorClassifier` | The classifier of the bundle: purposes, link previews and robots known by their patterns. |
| `Application\ShortLinkDomain`, `ShortLinkDomains` | The short link domains, by host and by locale. |
| `Application\ShortLinkQrCodes` | The QR code of a link's address, in SVG or PNG. |
| `Application\Contract\VisitRecorder` | Something told of each visit, after the response. |
| `Application\Contract\TargetCheck` | A check of a target before saving. |
| `Application\Contract\CreationThrottle` | The allowance of creations of an author. |
| `Application\Exception\ShortLinkRefused` | A link refused, with its reason and its message. |
| `Application\Exception\ShortLinkNotFound` | No link of that identifier. |
| `Application\Exception\InvalidCodec` | An alphabet, a minimum length, a domain or numbers the codec cannot work with. |
| `Domain\Model\ShortLink` | The link: its code, target, terms, count of visits of people and count of visits of machines. |
| `Domain\ValueObject\LinkTerms` | The status, query policy, label, locale and validity of a link. |
| `Domain\Enum\LinkState` | Active, scheduled, disabled, expired or out of visits. |
| `Domain\Enum\VisitorClass` | A person, a robot, a prefetch or a link preview. |
| `Domain\Contract\ShortLinkRepository` | Where the links are kept, and the atomic count of a visit. |
| `Domain\Exception\InvalidShortLink` | A link that cannot exist as given. |
| `Infrastructure\Doctrine\DoctrineShortLinkRepository` | The links with Doctrine ORM. |
| `Infrastructure\Http\ShortLinkListener` | Answers the short link domains before the routing and tells the recorders. |
| `Infrastructure\Http\ShortLinkAdminController` | The administration. |
| `Infrastructure\Form\ShortLinkType` | The form of a link. |
| `Infrastructure\RateLimiter\RateLimiterCreationThrottle` | The allowance on a Symfony rate limiter. |
| `Infrastructure\SafeHttp\SafeHttpTargetCheck` | The check of a target through phil/safe-http. |
| `Infrastructure\Analytics\AnalyticsVisitRecorder` | A visit of a person counted as a server event of phil/analytics-bundle. |
