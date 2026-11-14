# The history action

Every CRUD controller of an EasyAdmin dashboard whose entity implements `Phil\AuditBundle\Application\Contract\Auditable`
gets a "History" action, on its detail page and on each line of its index. It links to the history page of the record
the entity names, under the same dashboard: `admin_audit_history` with the `type` and `id` of
`auditSubject()`. Nothing is configured per controller.

```php
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;

#[ORM\Entity]
class Order implements Auditable
{
    public function auditSubject(): Subject
    {
        return Subject::of('order', (string) $this->id);
    }
}
```

The same interface lets `phil/audit-bundle` record the order's workflow transitions about that subject, so the
history the action opens holds them beside every other event recorded about the order. The trail is written by the
application — by its Doctrine listeners, its workflows, its own calls to `AuditTrail::record()` —; the action only
reads it.

## Who sees it

The action shows only to whoever is granted `PHIL_AUDIT_HISTORY` on the record, asked with its `Subject`. By default
that is the holders of the viewer's role and of `history_role`:

```yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER      # the whole log
    history_role: ROLE_ADMIN     # the history of each record, from its CRUD pages
```

A holder of `history_role` alone opens the history of the records the action is shown on, and nothing else of the
log: the history's rows are not links to their pages, and the link to the filtered log is not shown. The
application's `AuditAccessPolicy` narrows it further, record by record, whatever the access decision strategy
([extension-points.md](extension-points.md)).

## Turning it off, or replacing it

```yaml
phil_audit_viewer:
    history_action: false
```

To keep it everywhere but on one controller, or to label it otherwise there, give that controller an action of its
own by the same name: the extension adds its action to a page only when the controller has none by that name.

```php
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;

public function configureActions(Actions $actions): Actions
{
    return $actions->add(Crud::PAGE_DETAIL, Action::new(AuditHistoryActions::NAME, 'Changes')->linkToUrl(...));
}
```

The action is named `phil_audit_history`, labelled `action.history` in the `phil_audit_viewer` domain, with the icon
`fa fa-clock-rotate-left`; its link carries the CSS class `action-phil_audit_history` EasyAdmin gives every action.
