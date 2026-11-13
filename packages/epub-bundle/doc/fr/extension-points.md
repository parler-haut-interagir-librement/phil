<!-- phil-dev source-sha256=e217257c8eee3ee04a24f8627190ec251f23cd305ced2200cee06332cccfb999 -->
# Points d'extension

## Les services sur lesquels vous pouvez compter

| Identifiant | Remarques |
|---|---|
| `Phil\EpubBundle\Application\Epubs` | Public : écrit, conserve et construit des livres |
| `Phil\EpubBundle\Application\Port\EpubStorage` | Alias public ; faites-le pointer vers votre propre stockage |
| `Phil\EpubBundle\Infrastructure\Fonts\EpubFonts` | Public, lorsque `phil/fonts-bundle` est activé |
| `Phil\EpubBundle\Infrastructure\Media\MediaAssets` | Public, lorsque `phil/media-bundle` est activé |
| `phil_epub.source` | L'étiquette des sources de livres, donnée par `#[AsBookSource]` avec leur nom |
| `phil:epub:write` | La commande |

## `BookSource`

```php
interface BookSource
{
    public function build(string $id): Book;
}
```

| Contrat | |
|---|---|
| `build()` | Construit le livre entier à partir des enregistrements de l'application : le worker n'a rien d'autre que l'identifiant. Lance une exception lorsque l'enregistrement a disparu ; le handler ne fait pas de nouvelle tentative |

## `EpubStorage`

```php
interface EpubStorage
{
    public function store(string $path, string $bytes): string;
}
```

| Contrat | |
|---|---|
| `store()` | Conserve les octets sous le chemin relatif, refuse un chemin qui sort du stockage, et retourne l'endroit où ils sont conservés : l'emplacement que transporte `EpubStored`. Lance `StorageFailed` lorsqu'il n'y arrive pas, pour que le handler fasse une nouvelle tentative |

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
