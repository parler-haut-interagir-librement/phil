# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilReferencesBundle` | `final class`, `AbstractBundle` | Alias `phil_references`; `COLLECTOR_TAG`, `TARGET_TAG` |
| `Phil\Resource\ResourceReference` | `final readonly class`, from `phil/resource`, a dependency | A kind and an identifier |
| `Domain\ValueObject\Usage` | `final readonly class` | A source, a field, a target |
| `Domain\Exception\ReferencesException` | interface | Everything the bundle throws |
| `Domain\Exception\InvalidReference`, `StillReferenced` | exceptions | A bad value; a resource still used |
| `Domain\Repository\UsageRepository` | interface | The port |
| `Application\References` | `final readonly class` | Usages, deletion check, broken links |
| `Application\ReferenceCollector`, `ReferenceTarget` | interfaces | The application's sources and targets |
| `Application\Link` | `final readonly class` | A link a collector found |
| `Infrastructure\Doctrine\DbalUsageRepository` | repository | The port on Doctrine DBAL |
| `Infrastructure\Doctrine\UsageTable`, `UsageSchemaListener` | schema | `phil_reference_usage` in the ORM's schema |
| `Infrastructure\Doctrine\ReferenceTrackingListener` | Doctrine listener | Usages at each flush; deletion refused |
| `Infrastructure\Console\CheckReferencesCommand` | console command | `phil:references:check` |
