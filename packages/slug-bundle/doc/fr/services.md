<!-- phil-dev source-sha256=44c154aba4c3d0ab272698cc9d2979f537eee745a5d50115566a1e0c9ad5bdd3 -->
# Services

| Identifiant | Classe | Public | Rôle |
|---|---|---|---|
| `phil_slug.slugger.<name>` | `TransliteratingSlugger` ou `LeadingWordsRemoved` | non | Le générateur d'un nom |
| `Phil\Slug\Slugger` | alias | non | Le générateur par défaut |
| `Phil\Slug\Slugger $<name>Slugger` | alias | non | Le générateur d'un nom, par nom d'argument |
| `Phil\Slug\Sluggers` | `Sluggers` | oui | Chaque générateur par nom, et celui par défaut |
| `Phil\SlugBundle\Infrastructure\Twig\SlugExtension` | runtime Twig | non | Le filtre `phil_slug`, avec Twig |

## L'autoconnexion par nom

Un argument de constructeur typé `Slugger` reçoit le générateur par défaut ; un argument nommé `$<name>Slugger` reçoit
le générateur de ce nom, écrit en camel case (`directory_names` → `$directoryNamesSlugger`) :

```php
final readonly class DirectoryImport
{
    public function __construct(
        private Slugger $slugger,             // the default one
        private Slugger $directorySlugger,    // phil_slug.slugger.directory
    ) {}
}
```

Un bundle qui prend un générateur par identifiant de service — la clé `slugger` de `phil/taxonomy-bundle` — reçoit
`phil_slug.slugger.<name>`.

## Décorer un générateur

Chaque générateur nommé est un service à part : une application en change un en le décorant, comme n'importe quel
service Symfony :

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

Le décorateur prend la place du générateur partout : dans `Sluggers`, par les alias, et dans Twig.
