<!-- phil-dev source-sha256=5127e2093ea8b2b1aa5c0fd46affa6b96d4ea3eb4fa63a3280080f1270e77d3d -->
# Blocs

## Enregistrer un bloc

```php
$block = $blocks->save('article:42', $holder, $decoded, list: 'root', after: 'Zt0q');
$blocks->remove('article:42', $holder, 'aB3x');
```

`BlockSaves::save()` prend un bloc sous sa forme JSON — `{"id", "type", "data", "tunes"}`, décodée —, le vérifie avec
`phil/ujo` comme un document de ce seul bloc, et le renvoie sous forme canonique. Il n'est écrit que si le détenteur
détient le bail du bloc, ou celui de la ressource entière ; sinon, `LeaseLost`. Un bloc que `phil/ujo` refuse, ou un
emplacement qui ne peut pas en être un, donne `InvalidBlock`.

Son emplacement dans le document est fourni tel que le nomme l'éditeur de blocs :

| Argument | Signification |
|---|---|
| `list` | `root` ; l'identifiant d'un bloc qui contient des blocs, une bascule par exemple ; ou celui d'un bloc et d'une colonne, `aB3x:0` |
| `after` | Le bloc qui le précède dans cette liste ; `null` lorsqu'il vient en premier |

Un bloc qui en contient d'autres — une bascule, des colonnes — est enregistré avec les blocs qu'il contient.

## Le magasin de blocs de l'application

Sans magasin, les enregistrements sont seulement communiqués aux autres rédacteurs : le document est écrit lorsque l'un
d'eux soumet le formulaire. Avec un magasin, l'application conserve chaque bloc au moment de son enregistrement, afin
qu'un rédacteur qui ouvre la page plus tard lise les blocs les plus récents :

```php
use Phil\EditLockBundle\Application\BlockStore;

final readonly class DraftBlocks implements BlockStore
{
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void
    {
        // put the block in the draft of the resource: in place of its copy, or after $after in $list
    }

    public function remove(string $resource, string $blockId, Holder $holder): void
    {
        // take the block out of the draft
    }
}
```

Le magasin est appelé avant que l'enregistrement soit communiqué aux autres rédacteurs. Lorsque le formulaire est soumis
lui aussi, le document soumis contient les blocs de chaque rédacteur qui ont atteint cette page ; avec un magasin,
l'application décide qui l'emporte, de la soumission ou du brouillon.
