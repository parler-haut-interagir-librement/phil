# Extension points

## Services you can rely on

| Id | Notes |
|---|---|
| `Phil\FontsBundle\Application\FontFiles` | Public; replace it to read WOFF2 through another Brotli decoder: `new FontFiles(my_decoder(...))` |
| `Phil\FontsBundle\Application\FontCatalogue` | Public; the files of the configured directory. Build one of your own for another directory |
| `Phil\FontsBundle\Application\FontFaces` | Public; the rules, with URLs of your own through its closure |
| `Phil\FontsBundle\Application\Licences` | Public; the register, for an admin screen that shows the files to check |
| `Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts` | Public, with `phil/paged-media` |
| `Phil\FontsBundle\Infrastructure\Media\MediaFonts` | Public, with `phil/media-bundle` enabled |
| `phil:fonts:list`, `phil:fonts:licences`, `phil:fonts:css` | The commands |

## Another decoder

A decoder of your own takes the compressed bytes and the expected length, and returns the bytes or `false`. Register it
in place of the reader of the bundle:

```php
final class BrotliDecoderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(FontFiles::class)
            ->setFactory(null)
            ->setArguments([(new Definition(Closure::class))->setFactory([Closure::class, 'fromCallable'])->setArguments(['my_brotli_decode'])]);
    }
}
```

## A catalogue of your own

Several directories — the fonts of each theme, the fonts a tenant uploaded — are several catalogues:

```php
$catalogue = new FontCatalogue($themeDirectory, $fontFiles);
$css = new FontFaces($catalogue, '/themes/'.$theme.'/fonts')->css();
```
