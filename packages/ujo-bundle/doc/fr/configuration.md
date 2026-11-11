<!-- phil-dev source-sha256=56d3fe84b3369b209e5fd94b0f73a8cb5b2a240a6fff07f05cc6dd5a826984cd -->
# Configuration

```yaml
# config/packages/phil_ujo.yaml
phil_ujo:
    allow_raw: false        # whether documents may hold raw HTML blocks
    max_bytes: 4194304      # the largest UJO JSON read, in bytes
    max_depth: 8            # the deepest nesting of blocks inside blocks
    max_blocks: 10000       # the most blocks a document may hold, nested ones included
    max_html_length: 100000 # the longest HTML field, in characters
    max_raw_length: 50000   # the longest HTML of a raw block, in characters
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `allow_raw` | `false` | `true` ajoute le type `raw` au registre : son HTML est assaini avec le profil `raw`. Ne l'acceptez que de rédacteurs à qui vous confiez la structure des pages |
| `max_bytes` | `4194304` | Un JSON plus gros est refusé avant l'analyse. Au moins 1 |
| `max_depth` | `8` | Les blocs imbriqués plus profondément sont refusés. Au moins 1 |
| `max_blocks` | `10000` | Un document de plus de blocs, blocs imbriqués compris, est refusé. Au moins 1 |
| `max_html_length` | `100000` | Un champ HTML plus long, en caractères, est refusé avant son assainissement. Au moins 1 |
| `max_raw_length` | `50000` | La même chose pour le HTML d'un bloc `raw`. Au moins 1 |

Les limites de blocs et de longueur bornent ce que coûte l'affichage d'un document : son HTML est assaini de nouveau à
chaque rendu, par `ujo_html` comme par `Ujo::toHtml()`.

## Services

| Service | Construit comme |
|---|---|
| `Phil\Ujo\Type\BlockTypeRegistry` | `BlockTypeRegistry::default()`, avec `StandardTypes::raw()` quand `allow_raw` est vrai |
| `Phil\Ujo\Normalizer` | sur ce registre, les réglages et l'assainisseur par défaut, `max_depth`, `max_blocks`, `max_html_length` et `max_raw_length` |
| `Phil\Ujo\Html\HtmlRenderer` | `HtmlRenderer::default()` |
| `Phil\Ujo\Text\PlainText` | avec les conversions par défaut |
| `Phil\Ujo\Json\JsonDecoder` | avec `max_bytes` |
| `Phil\Ujo\Ujo` | sur les quatre ci-dessus ; public |

Les registres et les moteurs de rendu sont des valeurs immuables. Pour ajouter un type, un réglage, un moteur de rendu
ou une conversion, construisez la valeur dont l'application a besoin dans une fabrique à elle et déclarez cette
fabrique pour le service :

```php
// src/Ujo/UjoFactory.php
final class UjoFactory
{
    public static function types(): BlockTypeRegistry
    {
        return BlockTypeRegistry::default()->with(CalloutType::create());
    }

    public static function renderer(): HtmlRenderer
    {
        return HtmlRenderer::default()->with('callout', new CalloutRenderer());
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\Ujo\Type\BlockTypeRegistry:
        factory: ['App\Ujo\UjoFactory', 'types']
    Phil\Ujo\Html\HtmlRenderer:
        factory: ['App\Ujo\UjoFactory', 'renderer']
```

Les autres services, `Ujo` en premier, prennent par référence ceux qui sont redéfinis.
