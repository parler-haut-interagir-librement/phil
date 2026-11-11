# Services

| Id | Class | Public | Role |
|---|---|---|---|
| `phil_slug.slugger.<name>` | `TransliteratingSlugger` or `LeadingWordsRemoved` | no | The slugger of a name |
| `Phil\Slug\Slugger` | alias | no | The default slugger |
| `Phil\Slug\Slugger $<name>Slugger` | alias | no | The slugger of a name, by argument name |
| `Phil\Slug\Sluggers` | `Sluggers` | yes | Every slugger by name, and the default |
| `Phil\SlugBundle\Infrastructure\Twig\SlugExtension` | Twig runtime | no | The `phil_slug` filter, with Twig |

## Autowiring by name

A constructor argument typed `Slugger` gets the default slugger; one named `$<name>Slugger` gets the slugger of that
name, the name written in camel case (`directory_names` → `$directoryNamesSlugger`):

```php
final readonly class DirectoryImport
{
    public function __construct(
        private Slugger $slugger,             // the default one
        private Slugger $directorySlugger,    // phil_slug.slugger.directory
    ) {}
}
```

A bundle that takes a slugger by service id — `phil/taxonomy-bundle`'s `slugger` key — is given
`phil_slug.slugger.<name>`.

## Decorating a slugger

Each named slugger is a service of its own, so an application changes one by decorating it, as any Symfony service:

```php
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('phil_slug.slugger.titles')]
final readonly class NoDigits implements Slugger
{
    public function __construct(#[AutowireDecorated] private Slugger $inner) {}

    public function slug(string $text): Slug
    {
        return $this->inner->slug((string) preg_replace('/\d+/', '', $text));
    }
}
```

The decorator takes the place of the slugger everywhere: in `Sluggers`, through the aliases, and in Twig.
