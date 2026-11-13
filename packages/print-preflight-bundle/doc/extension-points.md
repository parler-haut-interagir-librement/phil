# Extension points

## Services you can rely on

| Id | Notes |
|---|---|
| `Phil\PrintPreflightBundle\Application\Preflighter` | Public: check a document against a profile |
| `Phil\PrintPreflightBundle\Application\PrintProfiles` | Public: the profiles and their names |
| `Phil\PrintPreflightBundle\Application\MeasuredDocument` | Public: read what a browser measured |
| `Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages` | Public, with `phil/media-bundle` |
| `phil_print_preflight.check` | The tag of the checks of the application, given by `#[AsPreflightCheck]` |
| `phil:print-preflight:check` | The command |

## `FontResolver`

```php
namespace Phil\PrintPreflightBundle\Application\Port;

interface FontResolver
{
    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont;
}
```

| Contract | |
|---|---|
| Returns | The `UsedFont` of the file the application serves for that face, with the embedding permissions of its `OS/2` table and the `embedded` and `subset` given |
| Throws | When it holds no such face: a font the preflight cannot vouch for is not passed silently |

Alias the port to a resolver of your own — fonts kept in a database, uploaded per tenant — and the catalogue's is left
aside:

```php
final readonly class TenantFontResolver implements FontResolver
{
    public function __construct(private TenantFonts $fonts) {}

    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont
    {
        return UsedFont::fromFont($this->fonts->face($family, $weight, $style), $embedded, $subset);
    }
}
```

```yaml
services:
    Phil\PrintPreflightBundle\Application\Port\FontResolver: '@App\Print\TenantFontResolver'
```

## A check of your own

See [Checking](checking.md#checks-of-the-application). A check returns findings, never throws for what it finds, and
uses a code of its own namespace so a profile can change its severity or ignore it.
