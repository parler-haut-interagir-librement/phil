# The access model

The bundle builds a `Phil\ResourcePermissions\Model\Model` from its configuration, as `AccessModel::create()`:

| Kind | Relations |
|---|---|
| `member` | none: a subject, `member:<id>` |
| `visitor` | none: a subject; a visitor is `visitor:anonymous` |
| `visibility` | none: the marker of an item's visibility, `visibility:members` |
| `space` | `admin`: members and groups' members; `member`: members, groups' members, and every admin |
| `group` | `member`: members |
| each item kind | `owner`, `space`, `visibility`, then one relation per sharing level |

Each sharing level accepts a member (`member`), every member (`member:*`), every visitor (`visitor:*`), the members of
a group (`group#member`), and the members or admins of a space (`space#member`, `space#admin`), and includes the level
before it — the widest including the owner:

```text
manage  = [member, member:*, visitor:*, group#member, space#member, space#admin] or owner
edit    = [same]                                                              or manage
comment = [same]                                                              or edit
view    = [same]                                                              or comment
```

So the owner holds every level, an editor comments and views, and a visibility, written on `view`, opens only viewing.
Admins of a space hold no level on an item by that alone: an item visible to its owner alone is visible to no admin.

## Adding to the model

A service implementing `Application\Contract\ModelContributor` is tagged `phil_access.model_contributor` on its own
with autoconfiguration. It receives the kinds by name and returns them, with its own added — a tree of folders, a
relation of the application:

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

The model is checked when built: a relation naming an undeclared kind stops the container.

## The services of the model

`RelationCheck`, `RelationWriter`, `TreePermissions` and `TupleStore` of `phil/resource-permissions` are services, for
what the bundle's own services do not cover: a relation a contributor added, the permissions per node of a tree.
