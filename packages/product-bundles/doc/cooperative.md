# Cooperative bundles

A cooperative bundle gathers the products of several sellers — creators, small presses, shops — under one bundle,
and shares each sale among them.

## Proposing

`CooperativeBundle::propose($sku, $host, $weights, $charity)` makes a draft:

| Part | Rule |
|---|---|
| `sku` | The bundle's SKU, as `ProductBundle` names it. |
| `host` | The participant who proposes it, one of the participants. |
| `weights` | From 2 to 50 participants, each an identifier of up to 255 printable characters, weighing 1 to 10,000. |
| `charity` | A `Rate` from 0 to 100 % taken from every sale first; none by default. |

Until the first approval, the host may change the participants and their weights with `reweigh()`, and the charity's
share with `withCharity()`.

## Approving and the lock

Each participant approves with `approve($participant, $at)`; approving again changes nothing, and someone who does not
take part is refused. The first approval locks the bundle: from then on `reweigh()` and `withCharity()` throw
`CooperativeRefused`, so that no participant approves one split and is paid by another. `lockedAt` keeps the earliest
instant of approval; `pending()` lists who has not approved yet.

The bundle is on sale once every participant approved: `isOnSale()`.

## Sharing a revenue

`shares(Money $revenue)` reckons the parts of a revenue: the charity's first, when it has a share, then the rest by
weight, in the order the participants were invited. The parts add up to the revenue to the unit. Before every
participant approved, it throws `CooperativeRefused`.

The library reckons the parts; paying them, and keeping what was paid, is the application's work.

## Storing

`toArray()` gives scalars only, instants as Unix timestamps; `fromArray()` reads it back and refuses a lock that is not
the instant of the earliest approval.
