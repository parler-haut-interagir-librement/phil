<!-- phil-dev source-sha256=67907a38921f156cf8775e20c68cb9dfb4b88455601caf4bfa0963a6bbc72bee -->
# Points d'extension

## Les services sur lesquels vous pouvez compter

| Identifiant | Remarques |
|---|---|
| `Phil\PrintPreflightBundle\Application\Preflighter` | Public : vérifie un document par rapport à un profil |
| `Phil\PrintPreflightBundle\Application\PrintProfiles` | Public : les profils et leurs noms |
| `Phil\PrintPreflightBundle\Application\MeasuredDocument` | Public : lit ce qu'un navigateur a mesuré |
| `Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages` | Public, avec `phil/media-bundle` |
| `phil_print_preflight.check` | L'étiquette des vérifications de l'application, donnée par `#[AsPreflightCheck]` |
| `phil:print-preflight:check` | La commande |

## `FontResolver`

```php
namespace Phil\PrintPreflightBundle\Application\Port;

interface FontResolver
{
    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont;
}
```

| Contrat | |
|---|---|
| Renvoie | Le `UsedFont` du fichier que l'application sert pour cette fonte, avec les permissions d'intégration de sa table `OS/2` et les `embedded` et `subset` donnés |
| Lève | Quand il ne contient aucune fonte de ce genre : une police dont le contrôle préalable ne peut pas répondre n'est pas laissée passer en silence |

Faites du port un alias vers un résolveur à vous — des polices gardées dans une base de données, téléversées par
locataire — et celui du catalogue est mis de côté :

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

## Une vérification à vous

Voir [Vérification](checking.md#vérifications-de-lapplication). Une vérification renvoie des constats, ne lève jamais
d'exception pour ce qu'elle trouve, et utilise un code de son propre espace de noms, pour qu'un profil puisse changer sa
gravité ou l'ignorer.
