# Extension points

## The access policy

The pages ask for `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` and `PHIL_AUDIT_EXPORT`; `AuditViewerVoter` grants them to
roles. An application that refuses some of them to the holders of those roles — exports out of office hours, the
history of an order to the manager of another shop — implements
`Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy` and names it in `access_policy`. The voter asks
it once a role holds, with the subject the attribute is asked with:

| Attribute | Subject | Asked for |
|---|---|---|
| `PHIL_AUDIT_VIEW` | `null` | the log page, the chain page |
| `PHIL_AUDIT_VIEW` | `AuditRow` | the row page, and each row the log page lists |
| `PHIL_AUDIT_VIEW` | the segment's name | the verification of a segment, and each segment the chain page lists |
| `PHIL_AUDIT_HISTORY` | `Subject` | the history of a record, and its "History" action |
| `PHIL_AUDIT_EXPORT` | `null` | the export as a whole, and its button on the log page |
| `PHIL_AUDIT_EXPORT` | `AuditRow` | each row the export reads |

What the policy refuses, the voter refuses. A page asked for with `null` is refused whole; a row or a segment refused
in a list is left out of it, so a page of the log may hold fewer rows than `page_size` and keep its link to the next
one, and the export reads at most `export_limit` rows, writing those allowed. A policy that decides per site answers
the `AuditRow` by its tenant (`$row->facts->tenant`) and the segment by `SiteSegment::belongsTo()`:

```php
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final readonly class ShopAuditPolicy implements AuditAccessPolicy
{
    public function __construct(private Orders $orders) {}

    public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (AuditViewerVoter::HISTORY !== $attribute || !$subject instanceof Subject || 'order' !== $subject->type) {
            return true;
        }

        $user = $token->getUser();

        return $user instanceof ShopManager && $this->orders->shopOf($subject->id) === $user->shop();
    }
}
```

```php
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\SiteSegment;

final readonly class SiteAuditPolicy implements AuditAccessPolicy
{
    public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof SiteAuditor) {
            return false;
        }

        return match (true) {
            $subject instanceof AuditRow => $subject->facts->tenant === $user->site(),
            \is_string($subject) => SiteSegment::belongsTo($subject, $user->site()),
            default => true,
        };
    }
}
```

The history example above, configured:

```yaml
phil_audit_viewer:
    history_role: ROLE_SHOP_MANAGER
    access_policy: App\Audit\ShopAuditPolicy
```

The policy narrows under every access decision strategy, because the voter consults it before it grants. A voter of
the application's own that refuses does not: under `affirmative`, Symfony's default, one voter that grants is enough,
and `AuditViewerVoter` grants to the role. Restrict through the policy, never through a second voter.

## Voters of your own

A voter of the application for the same attributes widens access under `affirmative`: it grants what the roles do not,
such as exports to a compliance officer who holds no role of the viewer. Under `consensus` the majority decides, and
under `unanimous` `AuditViewerVoter` refuses what the role lacks, so such a voter grants nothing more there.

```php
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;

/** @extends Voter<string, mixed> */
final class ComplianceExportVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return AuditViewerVoter::EXPORT === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof ComplianceOfficer;
    }
}
```

## The payload reader

A row's payload is the bytes of the application's `CanonicalSerializer`. When the application aliases
`Phil\AuditBundle\Application\Contract\PayloadReader` to a service that reads them back, the row and history pages
show its fields; without one, or for a version it does not read (`null`), they show the bytes as stored. The port is
`phil/audit-bundle`'s, described in its `doc/extension-points.md`.

## The actor of an export

The export names its actor through `phil/audit-bundle`'s `ActorResolver`. With SecurityBundle, that is the user's
`auditActorId()` when its class implements `AuditActor`; an application aliases `ActorResolver` to its own service to
decide otherwise.

## The history action

`AuditHistoryActions` is an EasyAdmin actions extension; turn it off with `history_action: false`, or keep it and give
one controller an action of its own by the same name, `AuditHistoryActions::NAME`, which the extension then leaves
alone ([history-action.md](history-action.md)).

## Templates

Every page extends `@EasyAdmin/page/content.html.twig`, so the dashboard's layout, menu and theme apply. To change one,
override it in `templates/bundles/PhilAuditViewerBundle/`:

| Template | Page | Variables |
|---|---|---|
| `log.html.twig` | The log | `filters` (`AuditFilters`), `page` (`AuditPage` or `null`), `timezone`, `exportable`, `csrf_id` |
| `row.html.twig` | A row | `row` (`AuditRow`), `fields` (array or `null`), `timezone` |
| `history.html.twig` | A history | `subject` (`Subject`), `page`, `fields` (one per row), `timezone`, `viewer` (whether the log may be read too) |
| `chain.html.twig` | The chain | `sites` (lists of `SegmentStatus` by tenant, `''` for the shared segments, first), `timezone` |
| `segment.html.twig` | A segment | `status` (`SegmentStatus`, verified), `timezone` |
| `_rows.html.twig`, `_fields.html.twig` | The table of rows, a payload | `rows`; `fields` and `row` |

## Translations

The words are in the `phil_audit_viewer` domain, in English and French. An application translates them into another
language, or changes them, with a `translations/phil_audit_viewer.<locale>` file of its own.
