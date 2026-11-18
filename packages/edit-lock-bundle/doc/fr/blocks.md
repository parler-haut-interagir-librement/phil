<!-- phil-dev source-sha256=2c318f154a2f7da5ab85a94b90baa5db176ee438e42d7424cb2814bb33bddc86 -->
# Blocs

## Enregistrer un bloc

```php
$block = $blocks->save('article:42', $holder, $decoded, list: 'root', after: 'Zt0q');
$blocks->remove('article:42', $holder, 'aB3x');
```

`BlockSaves::save()` prend un bloc sous sa forme JSON — `{"id", "type", "data", "tunes"}`, décodée —, le vérifie avec
`phil/ujo` comme un document de ce seul bloc, et le renvoie sous forme canonique. Il n'est écrit que si le détenteur
détient le bail du bloc, ou celui de la ressource entière ; sinon, `LeaseLost`. Un bloc que `phil/ujo` refuse, ou un
emplacement qui ne peut pas en être un, donne `InvalidBlock`. Une revendication que le magasin a cessé d'attendre donne
`LeaseHeld`, sans bail : rien n'a été écrit, et l'éditeur réessaie.

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
use Phil\EditLockBundle\Domain\ValueObject\Fence;

final readonly class DraftBlocks implements BlockStore
{
    public function save(
        string $resource,
        Block $block,
        string $list,
        ?string $after,
        Holder $holder,
        Fence $fence,
    ): void {
        // put the block in the draft of the resource: in place of its copy, or after $after in $list
    }

    public function remove(string $resource, string $blockId, Holder $holder, Fence $fence): void
    {
        // take the block out of the draft
    }
}
```

Le magasin est appelé avant que l'enregistrement soit communiqué aux autres rédacteurs.

## Les écritures sous un bail

Un bail peut expirer, ou être repris, à tout moment. Un enregistrement qui vérifierait le bail, puis écrirait le bloc,
laisserait passer une écriture après la perte du bail si la requête faisait une pause entre les deux. `BlockSaves`
referme cette fenêtre avec `EditLocks::whileHolding()` : le bail est vérifié, et le magasin de blocs appelé, dans une
seule revendication sur la ressource, la même revendication que celle où s'exécutent la prise, les pulsations et les
reprises. Aucune autre revendication sur la ressource ne s'exécute entre la vérification et l'écriture.

- **Avec le magasin Doctrine**, la revendication est une transaction qui verrouille la ligne de la ressource dans
  `phil_edit_lease_resource`. Un magasin de blocs qui écrit sur la même connexion écrit dans cette transaction :
  l'écriture est conservée ou annulée avec la revendication, et une reprise demandée entre-temps l'attend, puis trouve
  le bail à reprendre. Un magasin de blocs qui écrit ailleurs écrit quand même avant qu'une autre revendication
  commence.
- **Chaque écriture porte une `Fence`** (une clôture) : sa clé, son détenteur et le numéro de sa revendication, plus
  grand que celui de toute revendication antérieure sur la ressource. Un magasin qui pourrait appliquer une écriture
  plus tard — sur une autre base de données, par une file, après une nouvelle tentative — conserve ce numéro avec le
  bloc et n'écrit que si le nouveau est plus grand, par une seule instruction conditionnelle dont il vérifie le nombre
  de lignes :

```php
$applied = $connection->executeStatement(
    'UPDATE draft_block SET body = ?, fence = ? WHERE resource = ? AND id = ? AND fence < ?',
    [$body, $fence->number, $resource, $block->id, $fence->number],
);

if (0 === $applied) {
    // insert the block if it is new; otherwise a newer write is already kept, and this one is dropped
}
```

La revendication retient la ligne de la ressource aussi longtemps que le magasin de blocs le prend : un magasin de
blocs écrit donc un bloc et rend la main. Les autres rédacteurs de la ressource l'attendent, dans le délai de verrou de
la base de données, après quoi leur revendication abandonne et est réessayée.

## Le formulaire soumis

Les baux protègent l'enregistrement des blocs, pas le formulaire de la page. Lorsqu'un rédacteur soumet le formulaire,
le document soumis contient les blocs qu'avait la page de ce rédacteur, et l'application l'enregistre comme tout
formulaire : la dernière soumission l'emporte, quoi qu'il ait été enregistré depuis son chargement. Avec un magasin de
blocs, l'application décide qui l'emporte, de la soumission ou du brouillon, par exemple en refusant une soumission dont
le brouillon a changé après le chargement de la page, ou en gardant le brouillon et en ignorant les blocs que porte le
formulaire.
