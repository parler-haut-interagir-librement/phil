# Hosts and shops

One application can serve several tenants, each on its own domain: several shops, a sub-site per event, a site per
club. The bundle enters the tenant a request's host serves, before the firewall, and describes the shops among those
tenants so that a commerce bundle can tell which catalogues a shop sells and what to tag an order with.

## The tenant of each host

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        shop.example: general
        rose.example: rose
        tavern.example: tavern
```

Each key is a host, written alone, and each value the reference of the tenant it serves. `ConfiguredHostTenants`
reads the hosts as `phil/url` reads the host of an address:

| Written | Read as |
|---|---|
| `Shop.Example` | `shop.example`: hosts are compared in lowercase |
| `bücher.example` | `xn--bcher-kva.example`, the form a browser sends |
| `shop.localhost` | accepted, for development |
| `shop.example:8443`, `shop.example/fr` | refused: a host has no port, path, query or fragment |
| `192.0.2.1`, `user@shop.example` | refused: an IP address or credentials are not a host |
| `shop.example` and `SHOP.example` | refused: the same host listed twice |

A malformed host or reference stops the compilation of the container.

`Infrastructure\Http\ResolveHostTenant` runs on each main request, on `kernel.request`, before `security.firewall` (or
`debug.security.firewall`) and before `ResolveActiveTenant`. It reads the host as HttpFoundation does, after the
application's `framework.trusted_hosts`, and:

| Host | What the listener does |
|---|---|
| Serves a tenant | Enters the tenant, so that authentication and every query of the request act in it, then dispatches `Application\Event\HostTenantResolved` |
| Serves no tenant | Nothing: the request is left to `ResolveActiveTenant`, or runs in no tenant |

A request whose host serves a tenant is not resolved again by `ResolveActiveTenant`: the host decides, and
`ResolveActiveTenant` only checks that a signed-in account can reach that tenant, as
[integrations.md](integrations.md) describes. Sub-requests
share the main request's tenant. The tenant is left between requests with the rest of the context.

Configure `framework.trusted_hosts` with the same hosts: a request for a host the application does not serve is then
refused before any listener reads it. Until it is set, `ResolveHostTenant` logs a warning, through the `logger`
service, on the first request a host of a tenant serves, once per process: a worker serving many requests warns once,
a PHP-FPM process once per request it handles.

An application whose hosts live in a database implements `Application\Contract\HostTenants` instead, and aliases it:
see [extension-points.md](extension-points.md).

## Aliases of a host

A tenant's site can answer under other names: `www.` in front of its host, an old domain, a domain of the platform that
served it before it had its own. Each alias names the canonical host it stands for:

```yaml
phil_tenancy:
    hosts:
        rose.example: rose
    host_aliases:
        www.rose.example: rose.example
        rose.platform.example: rose.example
    redirect_host_aliases: true # the default
```

An alias is read as a host is, and serves the tenant of its canonical host. The compilation stops on an alias that is
also a host, an alias listed twice, or an alias of a host that is not in `hosts` — an alias of an alias included.

With `redirect_host_aliases`, `Infrastructure\Http\RedirectHostAlias` runs on each main request before
`ResolveHostTenant` and the firewall, and sends a request for an alias to its canonical host:

| Request | Answer |
|---|---|
| `GET https://www.rose.example/cart?item=1` | `301`, `Location: https://rose.example/cart?item=1` |
| `POST https://www.rose.example/cart` | `308`, which the client repeats with its body |
| `GET https://www.rose.example:8443/` | `301`, `Location: https://rose.example:8443/`: the scheme and the port are kept |
| A host that is no alias | Nothing: the request carries on |

The address comes from the configuration, never from the request: a visitor is only ever sent to one of the
application's hosts. With `redirect_host_aliases: false`, an alias serves its tenant where it is, and the application
tells search engines the canonical address itself. List the aliases in `framework.trusted_hosts` with the hosts.

An application whose aliases live in a database implements `Application\Contract\HostAliases` beside `HostTenants`
and registers `RedirectHostAlias` itself, as [extension-points.md](extension-points.md) shows.

## Shops

```yaml
phil_tenancy:
    shops:
        general:
            includes: [rose, tavern]
        rose: ~
        tavern: ~
```

Every shop is a tenant, with its own catalogue and cart. A general shop that includes other shops shows their
catalogues beside its own and takes payment for all of them at its single checkout. Each included shop keeps its own
shop on its own host. `Domain\Shop\ShopNetwork` holds these links, and refuses at compilation:

- a shop that includes itself;
- a shop listed twice in the same `includes`;
- a shop that is included and includes others: inclusion is one level deep.

| Method of `ShopNetwork` | Returns |
|---|---|
| `catalogues(TenantId $shop)` | The shop's own catalogue first, then those it includes, in their declared order |
| `sells(TenantId $shop, TenantId $catalogue)` | Whether a product of that catalogue may go in this shop's cart |
| `includedBy(TenantId $catalogue)` | The shops that include this one |
| `sharesAccounts(TenantId $one, TenantId $other)` | Whether a customer account signed in on one is signed in on the other: the same shop, a shop and one it includes, or two shops included by the same one |

### The shop of the request

`Application\Shop\ShopContext`, a public service, reads the active tenant against the network. It holds nothing of
its own.

| Method | Returns |
|---|---|
| `current(): ?TenantId` | The shop of the request, or `null` |
| `catalogues(): list<TenantId>` | The catalogues it sells; none outside a shop |
| `sells(TenantId $catalogue): bool` | Whether a product of that catalogue may go in its cart; never outside a shop |
| `origin(list<TenantId> $catalogues = []): OrderOrigin` | What an order placed now is tagged with; `NoActiveTenant` outside a shop |

### The origin of an order

`Domain\Shop\OrderOrigin` is what an order is tagged with: the shop it was placed in (`$shop`) and the distinct
catalogues of its lines (`$catalogues`). A catalogue the shop does not sell is refused with `InvalidValue`.
`spansCatalogues()` tells an order paid at a general shop's checkout for another shop's products. A central back office
lists the orders of every shop and still tells where each one came from.

```php
public function placeOrder(Cart $cart, ShopContext $shop): Order
{
    $origin = $shop->origin($cart->catalogues()); // the shop, and the catalogues of the cart's lines

    return Order::place($cart, $origin->shop->value, array_map(strval(...), $origin->catalogues));
}
```

### What a commerce bundle reads

| Need | What the bundle offers |
|---|---|
| Open the shop's catalogue and cart | `HostTenantResolved`, dispatched once the host's tenant is entered; `ShopContext::current()` |
| List the products of a general shop | `ShopContext::catalogues()`: the tenants whose products to show |
| Accept a product in the cart | `ShopContext::sells()` |
| Tag an order with its shop | `ShopContext::origin()` and `OrderOrigin` |
| Keep the customer signed in from one shop to the next | `ShopNetwork::sharesAccounts()`, applied by the scoped sessions: see [authentication.md](authentication.md) |

The Doctrine filter keeps every query inside one tenant. A query that lists the products of several catalogues,
such as a general shop's catalogue page, runs with the filter suspended and names its tenants itself, as
[doctrine-filter.md](doctrine-filter.md) describes for cross-tenant work.
