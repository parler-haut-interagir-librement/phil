# EasyAdmin

## The voter

With EasyAdmin enabled, `EasyAdminAccessVoter` votes on EasyAdmin's own attributes for the entities that are
`AccessibleItem`s:

| Attribute | Subject | Needs |
|---|---|---|
| `EA_ACCESS_ENTITY` | the `EntityDto` | the narrowest level, `view` |
| `EA_EXECUTE_ACTION` | the action and the `EntityDto` | the level `easyadmin.actions` maps the action to: `detail` → `view`, `edit` → `edit`, `delete` → `manage` |

Other entities, and actions mapped to no level, are left to the other voters.

EasyAdmin's own voter grants an entity or an action that no permission restricts. For this voter's refusal to count,
either the application's access decision manager decides unanimously:

```yaml
# config/packages/security.yaml
security:
    access_decision_manager:
        strategy: unanimous
```

or the CRUD controller names the levels as EasyAdmin permissions, which EasyAdmin hands to `AccessVoter`:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->setEntityPermission('access.view');
}

public function configureActions(Actions $actions): Actions
{
    return $actions
        ->setPermission(Action::EDIT, 'access.edit')
        ->setPermission(Action::DELETE, 'access.manage');
}
```

## A CRUD controller of declared scope

The voters decide one entity at a time; the index of a CRUD controller lists many. The application's controller
declares its scope — the identifiers the member may view — in its query:

```php
final class PageCrudController extends AbstractCrudController
{
    public function __construct(private readonly ItemAccess $access)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function createIndexQueryBuilder(SearchDto $search, EntityDto $entity, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $visible = $this->access->visible('page', limit: 1000);

        return parent::createIndexQueryBuilder($search, $entity, $fields, $filters)
            ->andWhere('entity.id IN (:visible)')
            ->setParameter('visible', $visible->ids());
    }
}
```

The scope lives in the application, where a reviewer reads it beside the entity; the bundle gives the identifiers.

## With `phil/tenancy-bundle`

On a site with several tenants, `phil/tenancy-bundle`'s Doctrine filter, `ActiveTenantFilter`, restricts every
tenant-scoped entity to the active tenant before any access check, and matches nothing when no tenant is active. The
two layers stack: the filter keeps a tenant's rows apart from another's, then the scope and the voters keep a member to
what is shared with them inside the tenant.

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            phil_tenancy:
                class: Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter
                enabled: true
```

A space per tenant — `phil_access.space`, or the space named when an item is registered — keeps the members and admins
of each tenant apart in the tuples as well.
