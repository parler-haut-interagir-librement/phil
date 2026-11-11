# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilTrashBundle` | `final class`, `AbstractBundle` | Alias `phil_trash`; the configuration, the mapping, the services; `HANDLER_TAG` |
| `Domain\Model\TrashItem` | entity | A resource in the trash |
| `Phil\Resource\ResourceReference` | `final readonly class`, from `phil/resource` | A kind and an identifier |
| `Domain\Exception\TrashException` | interface | Everything the trash throws |
| `Domain\Exception\InvalidTrashItem`, `ParentMissing`, `UnknownResource` | exceptions | A bad item; a parent gone; no handler |
| `Domain\Repository\TrashItemRepository` | interface | The port |
| `Application\Trash` | `final readonly class` | Trash, restore, purge |
| `Application\TrashHandler` | interface | How one kind goes and comes back |
| `Application\Captured` | `final readonly class` | What a handler keeps |
| `Application\Event\ResourceTrashed`, `ResourceRestored`, `TrashItemPurged` | events | Each step |
| `Infrastructure\Doctrine\DoctrineTrashItemRepository` | repository | The port on Doctrine ORM |
| `Infrastructure\Console\PurgeTrashCommand` | console command | `phil:trash:purge` |
