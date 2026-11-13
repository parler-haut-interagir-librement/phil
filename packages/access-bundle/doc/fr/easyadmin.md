<!-- phil-dev source-sha256=e87c0bdfa7df40dbd45c01904601c671a6b48cfa7572f45e21491c2ac8e772d9 -->
# EasyAdmin

## Le voter

Quand EasyAdmin est activé, `EasyAdminAccessVoter` vote sur les attributs propres à EasyAdmin pour les entités qui sont
des `AccessibleItem` :

| Attribut | Sujet | Exige |
|---|---|---|
| `EA_ACCESS_ENTITY` | l'`EntityDto` | le niveau le plus étroit, `view` |
| `EA_EXECUTE_ACTION` | l'action et l'`EntityDto` | le niveau auquel `easyadmin.actions` associe l'action : `detail` → `view`, `edit` → `edit`, `delete` → `manage` |

Les autres entités, et les actions associées à aucun niveau, sont laissées aux autres voters.

Le voter propre à EasyAdmin accorde une entité ou une action qu'aucune permission ne restreint. Pour que le refus de ce
voter compte, soit le gestionnaire de décision d'accès de l'application décide à l'unanimité :

```yaml
# config/packages/security.yaml
security:
    access_decision_manager:
        strategy: unanimous
```

soit le contrôleur CRUD nomme les niveaux comme permissions d'EasyAdmin, qu'EasyAdmin remet à `AccessVoter` :

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

## Un contrôleur CRUD à portée déclarée

Les voters décident une entité à la fois ; l'index d'un contrôleur CRUD en liste plusieurs. Le contrôleur de
l'application déclare sa portée — les identifiants que le membre peut voir — dans sa requête :

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

La portée vit dans l'application, où un relecteur la lit à côté de l'entité ; le bundle donne les identifiants.

## Avec `phil/tenancy-bundle`

Sur un site à plusieurs locataires, le filtre Doctrine de `phil/tenancy-bundle`, `ActiveTenantFilter`, restreint chaque
entité propre à un locataire au locataire actif avant toute vérification d'accès, et ne trouve rien quand aucun
locataire n'est actif. Les deux couches s'empilent : le filtre garde les lignes d'un locataire séparées de celles d'un
autre, puis la portée et les voters gardent un membre à ce qui est partagé avec lui à l'intérieur du locataire.

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            phil_tenancy:
                class: Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter
                enabled: true
```

Un espace par locataire — `phil_access.space`, ou l'espace nommé à l'enregistrement d'un élément — garde aussi
séparés, dans les tuples, les membres et les administrateurs de chaque locataire.
