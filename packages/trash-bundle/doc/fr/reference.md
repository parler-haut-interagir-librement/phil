<!-- phil-dev source-sha256=b78cb05cd6dab7f4f55e0ab6db18f2ad9bdc61760d2d5863924ea912a9bb8f69 -->
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
| `Infrastructure\Doctrine\BinaryKeysListener` | écouteur | Les clés de `phil_trash_item` en `utf8mb4_bin` sur MySQL et MariaDB, au `loadClassMetadata` de l'ORM ([installation.md](installation.md#collation-des-clés)) |
| `Infrastructure\Console\PurgeTrashCommand` | commande console | `phil:trash:purge` |
