<!-- phil-dev source-sha256=65c998ccd19e5b57991fc454ff91a1b8f261e6790242510c595b7c46e90849c2 -->
# Points d'extension

## Les services sur lesquels vous pouvez compter

| Id | Remarques |
|---|---|
| `Phil\FontsBundle\Application\FontFiles` | Public ; remplacez-le pour lire WOFF2 avec un autre décodeur Brotli : `new FontFiles(my_decoder(...))` |
| `Phil\FontsBundle\Application\FontCatalogue` | Public ; les fichiers du répertoire configuré. Construisez le vôtre pour un autre répertoire |
| `Phil\FontsBundle\Application\FontFaces` | Public ; les règles, avec vos propres URL grâce à sa fermeture |
| `Phil\FontsBundle\Application\Licences` | Public ; le registre, pour un écran d'administration qui montre les fichiers à vérifier |
| `Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts` | Public, avec `phil/paged-media` |
| `Phil\FontsBundle\Infrastructure\Media\MediaFonts` | Public, avec `phil/media-bundle` activé |
| `phil:fonts:list`, `phil:fonts:licences`, `phil:fonts:css` | Les commandes |

## Un autre décodeur

Votre décodeur reçoit les octets compressés et la longueur attendue, et retourne les octets ou `false`. Enregistrez-le à
la place du lecteur du bundle :

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

## Votre propre catalogue

Plusieurs répertoires (les polices de chaque thème, les polices téléversées par un locataire) forment plusieurs
catalogues :

```php
$catalogue = new FontCatalogue($themeDirectory, $fontFiles);
$css = new FontFaces($catalogue, '/themes/'.$theme.'/fonts')->css();
```
