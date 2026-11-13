# Extension points

## Services you can rely on

| Id | Notes |
|---|---|
| `Phil\EpubBundle\Application\Epubs` | Public: write, keep and build books |
| `Phil\EpubBundle\Application\Port\EpubStorage` | Public alias; point it to a storage of your own |
| `Phil\EpubBundle\Infrastructure\Fonts\EpubFonts` | Public, with `phil/fonts-bundle` enabled |
| `Phil\EpubBundle\Infrastructure\Media\MediaAssets` | Public, with `phil/media-bundle` enabled |
| `phil_epub.source` | The tag of the book sources, given by `#[AsBookSource]` with their name |
| `phil:epub:write` | The command |

## `BookSource`

```php
interface BookSource
{
    public function build(string $id): Book;
}
```

| Contract | |
|---|---|
| `build()` | Builds the whole book from the application's records: the worker has nothing but the identifier. Throws when the record is gone; the handler does not retry it |

## `EpubStorage`

```php
interface EpubStorage
{
    public function store(string $path, string $bytes): string;
}
```

| Contract | |
|---|---|
| `store()` | Keeps the bytes under the relative path, refusing one that leaves the storage, and returns where they are kept: the location `EpubStored` carries. Throws `StorageFailed` when it cannot, so the handler retries |

```php
final readonly class FlysystemEpubStorage implements EpubStorage
{
    public function __construct(private FilesystemOperator $books) {}

    public function store(string $path, string $bytes): string
    {
        try {
            $this->books->write($path, $bytes);
        } catch (FilesystemException $exception) {
            throw StorageFailed::because($exception->getMessage(), $exception);
        }

        return $path;
    }
}
```

```yaml
services:
    Phil\EpubBundle\Application\Port\EpubStorage: '@App\Books\FlysystemEpubStorage'
```
