<!-- phil-dev source-sha256=688ebc8146d3cbe9e1be70ef3ffd1033602e4a1fe2a1b305c9088203b16279a5 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilTrashBundle` | `final class`, `AbstractBundle` | Alias `phil_trash` ; la configuration, le mapping, les services ; `HANDLER_TAG` |
| `Domain\Model\TrashItem` | entité | Une ressource dans la corbeille |
| `Phil\Resource\ResourceReference` | `final readonly class`, de `phil/resource` | Un type et un identifiant |
| `Domain\Exception\TrashException` | interface | Tout ce que lance la corbeille |
| `Domain\Exception\InvalidTrashItem`, `ParentMissing`, `UnknownResource` | exceptions | Un élément invalide ; un parent disparu ; aucun gestionnaire |
| `Domain\Repository\TrashItemRepository` | interface | Le port |
| `Application\Trash` | `final readonly class` | Mise à la corbeille, restauration, purge |
| `Application\TrashHandler` | interface | Comment un type part et revient |
| `Application\Captured` | `final readonly class` | Ce que garde un gestionnaire |
| `Application\Event\ResourceTrashed`, `ResourceRestored`, `TrashItemPurged` | événements | Chaque étape |
| `Infrastructure\Doctrine\DoctrineTrashItemRepository` | dépôt | Le port sur Doctrine ORM |
| `Infrastructure\Console\PurgeTrashCommand` | commande console | `phil:trash:purge` |
