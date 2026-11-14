# Resolution and switching

`Phil\TenancyBundle\Domain\Resolution\ActiveTenantRule` holds the two rules of the active tenant as pure static
functions of the reachable set. Neither asks the identity provider anything. `ActiveTenant` applies them to the
application's ports; call the rule directly when you hold the values yourself.

## The resolution rule

```php
ActiveTenantRule::resolve(?TenantId $remembered, ReachableTenants $reachable): Resolution
```

| Remembered choice | Reachable set | Outcome | Tenant | `staleChoiceDropped` |
|---|---|---|---|---|
| reachable | contains it | `Active` | the remembered one | `false` |
| not reachable | exactly one tenant | `Active` | the only tenant | `true` |
| not reachable | several tenants | `SelectionRequired` | none | `true` |
| not reachable | empty | `NoneReachable` | none | `true` |
| none | exactly one tenant | `Active` | the only tenant | `false` |
| none | several tenants | `SelectionRequired` | none | `false` |
| none | empty | `NoneReachable` | none | `false` |

In words:

1. A remembered choice that is still reachable is the active tenant.
2. A remembered choice that is no longer reachable is dropped, never replaced by a neighbour, and the caller is
   told to forget it.
3. Without a usable choice, a single reachable tenant is active, since there is nothing to choose.
4. With several, a selection is required: the first tenant of a list is an accident of ordering, not a decision.
5. With none, no tenant is active, and the account stays authenticated.

**Why a dropped choice must be forgotten.** A membership that is revoked and later granted again is a new
decision; the old choice must not quietly come back with it. `ActiveTenant::resolve()` calls
`ActiveTenantStore::forget()` for you.

## The switch rule

```php
ActiveTenantRule::switchTo(TenantId $requested, ReachableTenants $reachable): TenantId
```

Returns the requested tenant when the set contains it, and throws `TenantNotReachable` otherwise, including when
the set is empty. `ActiveTenant::switchTo()` remembers the returned tenant; on a refusal the remembered choice is
left as it was.

## `Resolution`

`Phil\TenancyBundle\Domain\Resolution\Resolution`, immutable.

| Member | Behaviour |
|---|---|
| `public Outcome $outcome` | `Active`, `SelectionRequired` or `NoneReachable` |
| `public ReachableTenants $reachable` | The set the resolution was made against; empty for `NoneReachable` |
| `public bool $staleChoiceDropped` | A remembered choice was no longer reachable and was ignored |
| `isActive(): bool` | `Outcome::Active === $outcome` |
| `tenant(): TenantId` | The active tenant; throws `NoActiveTenant` otherwise (`selectionRequired()` or `noneReachable()`) |
| `tenantOrNull(): ?TenantId` | The active tenant, or `null` |
| `static active(TenantId, ReachableTenants, bool $staleChoiceDropped = false)` | Named constructor |
| `static selectionRequired(ReachableTenants, bool $staleChoiceDropped = false)` | Named constructor |
| `static noneReachable(bool $staleChoiceDropped = false)` | Named constructor |

## `Outcome`

`Phil\TenancyBundle\Domain\Resolution\Outcome`, a string-backed enum.

| Case | Value | What the application does |
|---|---|---|
| `Active` | `active` | Sets the tenant on the Doctrine filter and carries on |
| `SelectionRequired` | `selection_required` | Shows its selector page, listing `$resolution->reachable` |
| `NoneReachable` | `none_reachable` | Keeps the account signed in, in no tenant; tenant-scoped data stays invisible |

## The selector

The bundle owns the selector's **rules** and its **switch**: resolution says when a selection is required, the
switch rule says what a selection may choose, and the `phil_tenancy_switch` route applies it
([integrations.md](integrations.md)). The application owns the **page**: its rendering, the drop-down, and whether
entering a tenant requires a fresh authentication at a higher assurance level (asked of the identity provider on a
fresh authorisation request, never remembered by it for a tenant).

```php
#[Route('/tenant/select', name: 'tenant_select', methods: ['GET'])]
public function select(): Response
{
    return $this->render('tenant/select.html.twig');
}
```

```twig
{# templates/tenant/select.html.twig #}
<form method="post" action="{{ path('phil_tenancy_switch') }}">
    <input type="hidden" name="target" value="{{ app.request.query.get('target') }}">
    {% for tenant in reachable_tenants() %}
        <button name="tenant" value="{{ tenant }}">{{ tenant }}</button>
    {% endfor %}
</form>
```

Name the route in `request.selector_route`: the listener sends an account that must choose to it, and leaves it
unresolved so that the account can reach it.
