# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: its orders, `SellableResolver` and `SellableAvailability` |
| `phil/standard-webhooks` | ^0.1@dev | Signing and verifying the messages; it requires `ext-sodium` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | Retail prices, costs and the ledger |
| `doctrine/orm` | ^3.7 | The mapping attributes of the entities, and the repository adapter |
| `doctrine/dbal` | ^4.5 | The `datetime_utc_immutable` type of every moment: stored and compared in UTC |
| `symfony/polyfill-php86` | ^1.37 | PHP's `\SortDirection`, which the queries hand the ORM, on PHP 8.5 |
| `psr/clock` | ^1.0 | The moment of a received message, of a delivery run and of a purge |
| `symfony/http-foundation` | ^8.2 | The controller partners post to |
| `symfony/uid` | ^8.2 | Message and ledger identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration tree |
| `symfony/http-client` | suggested | `HttpClientTransport`, which delivers the messages |
| `symfony/console` | suggested | The `phil:commerce-syndication:deliver` command |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-syndication-bundle symfony/http-client
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's recipe also writes
`config/packages/phil_commerce_syndication.yaml` on the environment variables `SYNDICATION_IDENTITY` and
`SYNDICATION_SIGNING_KEY`, left empty for the key, and the partners' route in
`config/routes/phil_commerce_syndication.yaml`. Without Flex:

Register the core and the module; the order of the two lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceSyndicationBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configuring

```yaml
# config/packages/phil_commerce_syndication.yaml
phil_commerce_syndication:
    identity: shop                                   # this application's name at its partners
    signing_key: '%env(SYNDICATION_SIGNING_KEY)%'    # a whsk_ Ed25519 secret key
    tolerance: 300                                   # seconds a message's timestamp may stray from now
    max_attempts: 8                                  # delivery attempts before a message is given up
    retention_days: 90                               # days a message is kept once delivered, given up or received
    cost_grace_days: 7                               # on a supplier: days a replaced cost still pays a forwarded line
```

| Key | Default | Meaning |
|---|---|---|
| `identity` | required | The code partners know this application by: the audience every message sent here must carry |
| `signing_key` | required | The secret key every outgoing message is signed with; its public half, `whpk_…`, goes to the partners |
| `tolerance` | `300` | How far, in seconds, a received message's timestamp may be from now; 1 or more |
| `max_attempts` | `8` | How many times a message is tried before it is given up; 1 to 20 |
| `retention_days` | `90` | How many days a message is kept once delivered, given up or received, before `phil:commerce-syndication:purge` deletes it; 1 or more |
| `cost_grace_days` | `7` | On a supplier: how many days a cost it published to a reseller still pays a forwarded line after the next price replaced it; 0 or more |

Generate a key pair once, keep the secret in the environment, and hand the public key to each partner:

```php
$secret = Phil\StandardWebhooks\Ed25519SecretKey::generate();
$secret->toString();               // whsk_… — SYNDICATION_SIGNING_KEY
$secret->publicKey()->toString();  // whpk_… — for the partners
```

## Routing

Partners post to one URL each. Route `POST` requests to the controller with a `partner` attribute:

```yaml
# config/routes/syndication.yaml
syndication:
    path: /syndication/{partner}
    methods: [POST]
    controller: Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController
    requirements:
        partner: '[a-z0-9][a-z0-9_.-]{0,62}'
```

## Scheduling delivery

Messages wait in the outbox. Run the delivery every minute, from cron or Symfony Scheduler:

```sh
bin/console phil:commerce-syndication:deliver --limit=100
```

Two runs at once are safe: each claims a message before sending it. Purge what is past its retention once a day:

```sh
bin/console phil:commerce-syndication:purge
```

## Storage

The services read repositories: with DoctrineBundle, the module registers them with its Doctrine adapter — generate a
migration for its nine tables —; without it, the application declares them with its own adapters.

| Table | Holds |
|---|---|
| `phil_commerce_syndication_partner` | The partners: code, role, endpoint, key, the SKUs fed and the share |
| `phil_commerce_syndication_fed_sku` | One row per reseller and SKU fed: `partner` and `sku` (primary key), `sku` indexed; kept by the `onFlush` listener `FedSkuIndex` from each partner's list |
| `phil_commerce_syndication_outgoing` | The outbox: id, partner, type, `data`, attempts, `next_attempt_at`, `delivered_at`, `given_up_at`, `claimed_until`, `last_error` |
| `phil_commerce_syndication_received` | The ids received: `partner` and `id` (primary key), type, `received_at` (indexed) |
| `phil_commerce_syndication_product` | The syndicated products, per supplier and SKU |
| `phil_commerce_syndication_ledger` | What each partner owes for each order, and its lines in `line_items` (JSON: SKU, quantity, unit cost); a return's credit under its `return_number`, empty for the order itself; unique by partner, order and return |
| `phil_commerce_syndication_shipment` | The parcels of forwarded orders: `partner` and `shipment` (primary key), `order_number` (indexed), `line_items` (JSON), `carrier`, `tracking`, `shipped_at` |
| `phil_commerce_syndication_return` | The returns: `partner` and `return_number` (primary key), `order_number`, `currency`, `requested` and `received` (JSON), `state`, `credit`, `carrier`, `tracking`, `requested_at`, `received_at`; indexed by partner and order |
| `phil_commerce_syndication_published_price` | On a supplier, each price published to a reseller: reseller, SKU, currency, retail, cost, `published_at` |

Every moment column is a `datetime_utc_immutable`: written in UTC and read back in UTC, whatever the zone of the
process.

### Migrating an existing database

- `phil_commerce_syndication_outgoing` gains `given_up_at` (indexed) and `claimed_until`, both nullable. Set
  `given_up_at` on the rows already given up — no `delivered_at`, no `next_attempt_at` — to their last attempt, or to
  the migration's moment, so that the retention can purge them; clear `ship_to` from their `data` and from that of
  the delivered rows.
- `phil_commerce_syndication_received` takes `(partner, id)` as its primary key instead of `id`, and an index on
  `received_at`.
- `phil_commerce_syndication_fed_sku` is new. Fill it from the partners' `skus` JSON column in the migration — one row
  per reseller and SKU —: a product update reaches only the resellers it lists. From then on the listener keeps it.
- `phil_commerce_syndication_ledger` gains `return_number` (empty on every existing row) and `line_items` (`[]` on every
  existing row, or the lines of the forwarded order when the outbox still holds its message); its unique constraint
  becomes partner, order and return. Without lines, an order is neither tracked nor returned.
- `phil_commerce_syndication_shipment` and `phil_commerce_syndication_return` are new.
- Moments already stored are UTC when the database ran in UTC; convert them otherwise.
- `phil_commerce_syndication_published_price` is new. **A supplier republishes its feed right after migrating** —
  `Publisher::publish()` for every SKU it feeds —: until a price is recorded, every forwarded order is refused.
