<!-- phil-dev source-sha256=526b5f90f7012384eace7fbb430ed595389486d014667ba7ce12785c59ddf2f7 -->
# Gestionnaires

Chaque type de ressource que l'application met à la corbeille a un `TrashHandler` ; avec l'autoconfiguration, le
bundle le trouve.

| Méthode | Ce qu'elle fait |
|---|---|
| `resourceKey()` | Le type : `page`, lettres minuscules, chiffres, `_`, `.` ou `-` |
| `supports($resource)` | Si ce gestionnaire garde cet objet |
| `capture($resource)` | Un `Captured` : l'identifiant, un titre pour la corbeille, ce qu'il faut pour la restaurer (du JSON), son parent |
| `remove($resource)` | Retire les lignes de la ressource, les siennes et celles de ses enfants, sans flush ; ses fichiers restent |
| `parentExists($parent)` | Si un parent existe encore |
| `restore($item, $parent)` | Ramène la ressource sous ce parent, et la retourne |
| `purge($item)` | Oublie ce que la ressource a laissé hors de la corbeille et de ses tables, ses fichiers ; souvent rien |

```php
final readonly class PageTrashHandler implements TrashHandler
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function resourceKey(): string { return 'page'; }

    public function supports(object $resource): bool { return $resource instanceof Page; }

    public function capture(object $resource): Captured
    {
        return new Captured($resource->id, $resource->title, ['title' => $resource->title, 'body' => $resource->body], $resource->parent ? ResourceReference::of('page', $resource->parent->id) : null);
    }

    public function remove(object $resource): void { $this->entityManager->remove($resource); }

    public function parentExists(ResourceReference $parent): bool { return null !== $this->entityManager->find(Page::class, $parent->id); }

    public function restore(TrashItem $item, ?ResourceReference $parent): object
    {
        $page = Page::restore($item->resource()->id, $item->payload(), $parent?->id);
        $this->entityManager->persist($page);

        return $page;
    }

    public function purge(TrashItem $item): void {}
}
```

La corbeille fait un seul flush, après le gestionnaire : la ressource quitte sa table et entre dans la corbeille en
même temps. Un gestionnaire ne fait jamais de flush et ne supprime jamais un fichier dans `remove()` : une ressource
restaurée a besoin de retrouver ses fichiers.

## Une ressource avec des fichiers

Un média de `phil/media-bundle` garde ses fichiers sur disque. Son gestionnaire retire la ligne, laisse les fichiers,
et ne les supprime que lorsque la corbeille oublie l'élément pour de bon :

```php
final readonly class MediaTrashHandler implements TrashHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private MediaStorage $storage) {}

    public function resourceKey(): string { return 'media'; }

    public function supports(object $resource): bool { return $resource instanceof Media; }

    public function capture(object $resource): Captured
    {
        return new Captured($resource->id, $resource->title() ?? $resource->file()->filename, ['storage_key' => $resource->storageKey() /* and what Media needs back */], null);
    }

    // Not MediaLibrary::delete(), which deletes the files and flushes.
    public function remove(object $resource): void { $this->entityManager->remove($resource); }

    public function parentExists(ResourceReference $parent): bool { return false; }

    public function restore(TrashItem $item, ?ResourceReference $parent): object { /* rebuild the Media from the payload */ }

    public function purge(TrashItem $item): void
    {
        $this->storage->deletePrefix('originals/'.$item->resource()->id);
        $this->storage->deletePrefix('formats/'.$item->resource()->id);
    }
}
```

## Mettre à la corbeille et restaurer

```php
$item = $trash->trash($page, trashedBy: 'sub-1');   // the handler removes it; a TrashItem keeps it
$trash->restore($item);                             // under its parent
```

**Un parent disparu** donne un `ParentMissing`, qui nomme la ressource et le parent : restaurez-la sous un autre,
`$trash->restore($item, ResourceReference::of('page', '7'))`. Une ressource qu'aucun gestionnaire ne prend en charge
donne un `UnknownResource`.

`TrashItemRepository::list($kind, $limit, $offset)` liste la corbeille, du plus récent au plus ancien, pour un écran
d'administration ; `get($id)` trouve un élément.
