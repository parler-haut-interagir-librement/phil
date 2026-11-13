<!-- phil-dev source-sha256=e32911d1a90e2c5153126df094d1551b08afecab57c3c95751aaf0675ee508bd -->
# Le modèle d'accès

Le bundle construit un `Phil\ResourcePermissions\Model\Model` à partir de sa configuration, avec
`AccessModel::create()` :

| Type | Relations |
|---|---|
| `member` | aucune : un sujet, `member:<id>` |
| `visitor` | aucune : un sujet ; un visiteur est `visitor:anonymous` |
| `visibility` | aucune : le marqueur de la visibilité d'un élément, `visibility:members` |
| `space` | `admin` : les membres et les membres des groupes ; `member` : les membres, les membres des groupes et chaque administrateur |
| `group` | `member` : les membres |
| chaque type d'élément | `owner`, `space`, `visibility`, puis une relation par niveau de partage |

Chaque niveau de partage accepte un membre (`member`), tous les membres (`member:*`), tous les visiteurs
(`visitor:*`), les membres d'un groupe (`group#member`), et les membres ou les administrateurs d'un espace
(`space#member`, `space#admin`), et inclut le niveau qui le précède — le plus large incluant le propriétaire :

```text
manage  = [member, member:*, visitor:*, group#member, space#member, space#admin] or owner
edit    = [same]                                                              or manage
comment = [same]                                                              or edit
view    = [same]                                                              or comment
```

Ainsi, le propriétaire détient chaque niveau, un éditeur commente et voit, et une visibilité, écrite sur `view`,
n'ouvre que la consultation. Les administrateurs d'un espace ne détiennent aucun niveau sur un élément du seul fait
qu'ils le sont : un élément visible à son propriétaire seul n'est visible d'aucun administrateur.

## Ajouter au modèle

Un service qui implémente `Application\Contract\ModelContributor` est étiqueté `phil_access.model_contributor` de
lui-même grâce à l'autoconfiguration. Il reçoit les types par nom et les retourne, avec les siens ajoutés — un arbre de
dossiers, une relation de l'application :

```php
final class FoldersContributor implements ModelContributor
{
    public function contribute(array $types): array
    {
        $types['folder'] = TypeDefinition::of('folder', [
            'parent' => Rewrite::direct('folder'),
            'viewer' => Rewrite::union(Rewrite::direct('member', 'group#member'), Rewrite::fromRelated('parent', 'viewer')),
        ]);
        $types['page'] = $types['page']->with(['folder' => Rewrite::direct('folder')]);

        return $types;
    }
}
```

Le modèle est vérifié à la construction : une relation qui nomme un type non déclaré arrête le conteneur.

## Les services du modèle

`RelationCheck`, `RelationWriter`, `TreePermissions` et `TupleStore` de `phil/resource-permissions` sont des services,
pour ce que les services du bundle ne couvrent pas : une relation qu'un contributeur a ajoutée, les permissions par
nœud d'un arbre.
