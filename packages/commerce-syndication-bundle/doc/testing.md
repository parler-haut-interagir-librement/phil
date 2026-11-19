# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Partner`, `OutgoingMessage`, `ReceivedMessage`, `SyndicatedProduct`, `FeedItem`, `LedgerEntry` | Every rule, the retry schedule, giving up, out-of-order descriptions, settlement |
| `Partners`, `Publisher`, `Courier`, `Ledger` | Feeding resellers and their costs, signing and delivering, retries, limits, suspended partners, balances |
| `Forwarder`, `SyndicatedSellables` | Grouping by supplier, the ledger, idempotence, refusals; resolution and availability |
| `Inbox`, `PartnerKey` | Verification by the partner's own key, audience, staleness, deduplication, each message type and its refusals |
| `ShipmentTracking`, `PartnerReturns`, `PartnerShipment`, `PartnerReturn`, `MessageData` | A supplier and a reseller, each with its own store, trading a parcel and a return through signed messages: each sent once, bounds against the ledger on both sides, a return received once and never beyond what was announced, credits equal on both sides and a differing one refused, the balances after the credit |
| `SyndicationWebhookController`, `HttpClientTransport`, `DeliverMessagesCommand` | The answers, the HTTP request, the command's limit |
| Mapping | Every column of the nine tables |
| `DoctrineSyndicationRepository`, `FedSkuIndex` | On SQLite in memory: the resellers fed a SKU through the index as lists change, the latest prices and the prices in effect read for many resellers or SKUs at once |
| `PhilCommerceSyndicationBundle` | The configuration and its bounds, the services with DoctrineBundle, nothing without, the mapping, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `SyndicationFlowTest` registers partners, queues the feed, receives a signed message through the
controller twice, settles the ledger, and stores a parcel, a return and its credit in a process on another time zone,
reading every moment back in UTC. Installed on its own, where FrameworkBundle is a development requirement,
the suite is skipped.

A fuzz target, `partner-messages`, feeds the reader of parcels and returns JSON nobody wrote, from the seeds of
`tests/Fuzz/corpus/partner-messages`: it returns a parcel or a return, or refuses the message with `InvalidValue`,
and never throws anything else nor warns. From the root of the monorepo: `composer fuzz -- partner-messages`.

An integration suite, `tests/Integration`, runs the Doctrine adapter on MySQL 8 and PostgreSQL 17 — its tables created
from the mapping in a process on another zone, moments read back in UTC, the claim and purge of a message, the prices in
effect, the ledger of an order and of its return, parcels and returns read back — when `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL` name a server, and is skipped otherwise:

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-syndication-bundle/tests/Integration
```

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-syndication-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/commerce-syndication-bundle/tests/Benchmark
```
