# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\WellKnownBundle\PhilWellKnownBundle` | `AbstractBundle` | The `phil_well_known` configuration, the services and the pass |
| `…\Application\Contract\WellKnownDocument` | interface | `serve($request)` |
| `…\Application\Contract\WebFingerResolver` | interface | `resolve($resource)` |
| `…\Application\Contract\NodeInfoProvider` | interface | `software()`, `usage()`, `openRegistrations()`, `metadata()` |
| `…\Application\Contract\SecurityTxtSigner` | interface | `sign($text)` |
| `…\Application\Attribute\AsWellKnownDocument` | attribute | `path`, `route` |
| `…\Application\WebFinger` | `final readonly class` | Merges the descriptors of the resolvers, filters the links |
| `…\Application\StaticWebFingerResolver` | `WebFingerResolver` | The resources of the configuration |
| `…\Application\ConfiguredNodeInfoProvider` | `NodeInfoProvider` | The NodeInfo of the configuration |
| `…\Domain\ValueObject\WellKnownPath` | value object | `of($path, $route)`, `path`, `route` |
| `…\Domain\ValueObject\SecurityTxt` | value object | The fields, `expiresAt($date)`, `toString()` |
| `…\Domain\ValueObject\WebFingerQuery` | value object | `parse($query)`, `resource`, `rels` |
| `…\Domain\ValueObject\WebFingerResource` | value object | `parse($resource)`, `uri`, `scheme`, `user`, `host`, `isAccount()` |
| `…\Domain\ValueObject\WebFingerDescriptor` | value object | `subject`, `aliases`, `properties`, `links`, `merge()`, `withRels()`, `toArray()` |
| `…\Domain\ValueObject\WebFingerLink` | value object | `rel`, `type`, `href`, `template`, `titles`, `properties`, `toArray()` |
| `…\Domain\ValueObject\NodeInfo` | value object | The 2.1 document, its protocols and services, `check()`, `toArray()` |
| `…\Domain\ValueObject\NodeInfoSoftware` | value object | `name`, `version`, `repository`, `homepage` |
| `…\Domain\ValueObject\NodeInfoUsage` | value object | The optional counts |
| `…\Domain\ValueObject\Did` | value object | `of($did, $method)`, `did`, `method` |
| `…\Domain\Exception\WellKnownException` | interface | Every exception of the bundle |
| `…\Domain\Exception\InvalidValue` | `InvalidArgumentException` | A value a document refuses |
| `…\Infrastructure\Http\WellKnownController` | controller | Serves the document of the route, with its headers |
| `…\Infrastructure\Http\Document\*` | `WellKnownDocument` | `SecurityTxtDocument`, `ChangePasswordDocument`, `WebFingerDocument`, `NodeInfoDiscoveryDocument`, `NodeInfoDocument`, `AtprotoDidDocument`, `StaticDocument` |
| `…\Infrastructure\Http\Document\DocumentResponses` | `final class` | The answers, their media type and CORS |
| `…\Infrastructure\Routing\WellKnownRouteLoader` | route loader | The `phil_well_known` type |
| `…\Infrastructure\Monitoring\SecurityTxtExpiryCheck` | `HealthCheck` of phil/monitoring-bundle | `NAME`, `DEFAULT_WARNING_DAYS`; up, degraded before `Expires`, down after |
| `…\Infrastructure\DependencyInjection\WellKnownServices` | `final class` | Registers the services; the checks of the configuration |
| `…\Infrastructure\DependencyInjection\WellKnownPass` | compiler pass | Gathers the tagged documents |

## Services

| Id | When |
|---|---|
| `Phil\WellKnownBundle\Infrastructure\Http\WellKnownController` | Always; public |
| `Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader` | Always; `routing.loader` |
| `phil_well_known.document.<path>` | Each document turned on |
| `Phil\WellKnownBundle\Application\WebFinger`, `…\StaticWebFingerResolver` | With `webfinger` |
| `Phil\WellKnownBundle\Application\ConfiguredNodeInfoProvider` | With `nodeinfo` and no `provider` |
| `Phil\WellKnownBundle\Infrastructure\Monitoring\SecurityTxtExpiryCheck` | With `security_txt` and phil/monitoring-bundle; `phil_monitoring.health_check` |

## Constants

| Constant | Value |
|---|---|
| `WellKnownServices::DOCUMENT_TAG` | `phil_well_known.document` |
| `WellKnownServices::RESOLVER_TAG` | `phil_well_known.webfinger_resolver` |
| `WellKnownServices::NODEINFO_ROUTE` | `phil_well_known_nodeinfo_document` |
| `WellKnownRouteLoader::TYPE` | `phil_well_known` |
| `WellKnownRouteLoader::PARAMETER` | `phil_well_known.routes` |
| `WellKnownController::DOCUMENT` | `_phil_well_known` |
| `WellKnownPath::PREFIX` | `/.well-known/` |
| `NodeInfo::SCHEMA` | `http://nodeinfo.diaspora.software/ns/schema/2.1` |
