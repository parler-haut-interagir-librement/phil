<!-- phil-dev source-sha256=84600ada4665a9a677aa7a88207e57321f0496f41e4c56f42a68e6904ead9e5e -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\WellKnownBundle\PhilWellKnownBundle` | `AbstractBundle` | La configuration `phil_well_known`, les services et la passe |
| `…\Application\Contract\WellKnownDocument` | interface | `serve($request)` |
| `…\Application\Contract\WebFingerResolver` | interface | `resolve($resource)` |
| `…\Application\Contract\NodeInfoProvider` | interface | `software()`, `usage()`, `openRegistrations()`, `metadata()` |
| `…\Application\Contract\SecurityTxtSigner` | interface | `sign($text)` |
| `…\Application\Attribute\AsWellKnownDocument` | attribut | `path`, `route` |
| `…\Application\WebFinger` | `final readonly class` | Fusionne les descripteurs des résolveurs, filtre les liens |
| `…\Application\StaticWebFingerResolver` | `WebFingerResolver` | Les ressources de la configuration |
| `…\Application\ConfiguredNodeInfoProvider` | `NodeInfoProvider` | Le NodeInfo de la configuration |
| `…\Domain\ValueObject\WellKnownPath` | objet-valeur | `of($path, $route)`, `path`, `route` |
| `…\Domain\ValueObject\SecurityTxt` | objet-valeur | Les champs, `expiresAt($date)`, `toString()` |
| `…\Domain\ValueObject\WebFingerQuery` | objet-valeur | `parse($query)`, `resource`, `rels` |
| `…\Domain\ValueObject\WebFingerResource` | objet-valeur | `parse($resource)`, `uri`, `scheme`, `user`, `host`, `isAccount()` |
| `…\Domain\ValueObject\WebFingerDescriptor` | objet-valeur | `subject`, `aliases`, `properties`, `links`, `merge()`, `withRels()`, `toArray()` |
| `…\Domain\ValueObject\WebFingerLink` | objet-valeur | `rel`, `type`, `href`, `template`, `titles`, `properties`, `toArray()` |
| `…\Domain\ValueObject\NodeInfo` | objet-valeur | Le document 2.1, ses protocoles et ses services, `check()`, `toArray()` |
| `…\Domain\ValueObject\NodeInfoSoftware` | objet-valeur | `name`, `version`, `repository`, `homepage` |
| `…\Domain\ValueObject\NodeInfoUsage` | objet-valeur | Les nombres facultatifs |
| `…\Domain\ValueObject\Did` | objet-valeur | `of($did, $method)`, `did`, `method` |
| `…\Domain\Exception\WellKnownException` | interface | Chaque exception du bundle |
| `…\Domain\Exception\InvalidValue` | `InvalidArgumentException` | Une valeur qu'un document refuse |
| `…\Infrastructure\Http\WellKnownController` | contrôleur | Sert le document de la route, avec ses en-têtes |
| `…\Infrastructure\Http\Document\*` | `WellKnownDocument` | `SecurityTxtDocument`, `ChangePasswordDocument`, `WebFingerDocument`, `NodeInfoDiscoveryDocument`, `NodeInfoDocument`, `AtprotoDidDocument`, `StaticDocument` |
| `…\Infrastructure\Http\Document\DocumentResponses` | `final class` | Les réponses, leur type de média et CORS |
| `…\Infrastructure\Routing\WellKnownRouteLoader` | chargeur de routes | Le type `phil_well_known` |
| `…\Infrastructure\Monitoring\SecurityTxtExpiryCheck` | `HealthCheck` de phil/monitoring-bundle | `NAME`, `DEFAULT_WARNING_DAYS` ; en service, dégradée avant `Expires`, hors service après |
| `…\Infrastructure\DependencyInjection\WellKnownServices` | `final class` | Enregistre les services ; les vérifications de la configuration |
| `…\Infrastructure\DependencyInjection\WellKnownPass` | passe de compilation | Rassemble les documents étiquetés |

## Services

| Identifiant | Quand |
|---|---|
| `Phil\WellKnownBundle\Infrastructure\Http\WellKnownController` | Toujours ; public |
| `Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader` | Toujours ; `routing.loader` |
| `phil_well_known.document.<path>` | Chaque document activé |
| `Phil\WellKnownBundle\Application\WebFinger`, `…\StaticWebFingerResolver` | Avec `webfinger` |
| `Phil\WellKnownBundle\Application\ConfiguredNodeInfoProvider` | Avec `nodeinfo` et sans `provider` |
| `Phil\WellKnownBundle\Infrastructure\Monitoring\SecurityTxtExpiryCheck` | Avec `security_txt` et phil/monitoring-bundle ; `phil_monitoring.health_check` |

## Constantes

| Constante | Valeur |
|---|---|
| `WellKnownServices::DOCUMENT_TAG` | `phil_well_known.document` |
| `WellKnownServices::RESOLVER_TAG` | `phil_well_known.webfinger_resolver` |
| `WellKnownServices::NODEINFO_ROUTE` | `phil_well_known_nodeinfo_document` |
| `WellKnownRouteLoader::TYPE` | `phil_well_known` |
| `WellKnownRouteLoader::PARAMETER` | `phil_well_known.routes` |
| `WellKnownController::DOCUMENT` | `_phil_well_known` |
| `WellKnownPath::PREFIX` | `/.well-known/` |
| `NodeInfo::SCHEMA` | `http://nodeinfo.diaspora.software/ns/schema/2.1` |
