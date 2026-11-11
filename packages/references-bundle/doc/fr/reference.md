<!-- phil-dev source-sha256=81bb8f9ca73a33aaa68ed84e080403d82d4f023afb05afa75e0a7f505e967372 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilReferencesBundle` | `final class`, `AbstractBundle` | Alias `phil_references` ; `COLLECTOR_TAG`, `TARGET_TAG` |
| `Phil\Resource\ResourceReference` | `final readonly class`, de `phil/resource`, une dépendance | Un genre et un identifiant |
| `Domain\ValueObject\Usage` | `final readonly class` | Une source, un champ, une cible |
| `Domain\Exception\ReferencesException` | interface | Tout ce que lève le bundle |
| `Domain\Exception\InvalidReference`, `StillReferenced` | exceptions | Une mauvaise valeur ; une ressource encore utilisée |
| `Domain\Repository\UsageRepository` | interface | Le port |
| `Application\References` | `final readonly class` | Usages, vérification avant suppression, liens rompus |
| `Application\ReferenceCollector`, `ReferenceTarget` | interfaces | Les sources et les cibles de l'application |
| `Application\Link` | `final readonly class` | Un lien qu'a trouvé un collecteur |
| `Infrastructure\Doctrine\DbalUsageRepository` | dépôt | Le port sur Doctrine DBAL |
| `Infrastructure\Doctrine\UsageTable`, `UsageSchemaListener` | schéma | `phil_reference_usage` dans le schéma de l'ORM |
| `Infrastructure\Doctrine\ReferenceTrackingListener` | écouteur Doctrine | Usages à chaque *flush* ; suppression refusée |
| `Infrastructure\Console\CheckReferencesCommand` | commande console | `phil:references:check` |
