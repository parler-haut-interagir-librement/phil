# Agreements

The terms of each receipt line — a share or a commission — say what a consignor earns on each unit sold. The
**agreement** says the rest of what the shop and the consignor agreed: how long the units stay, what happens to those
that did not sell, and how often the consignor receives a statement.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;

$agreements->agree(
    Owner::consignor('author-17'),
    effectiveFrom: $clock->now(),
    termDays: 90,
    unsoldPolicy: UnsoldPolicy::ReturnToConsignor,
    frequency: StatementFrequency::Monthly,
    at: $clock->now(),
);
$entityManager->flush();
```

| Field | What it says |
|---|---|
| `termDays` | How many days the units stay, from their receipt: 1 to 3 650, or `null` for no end. |
| `unsoldPolicy` | What happens to the unsold units at the end: `ReturnToConsignor`, `Extend` or `BuyOut`. |
| `extensionDays`, `extensions` | With `Extend` only: the days each extension adds, and how many extensions, 1 to 12 — the term and every extension within 3 650 days. |
| `frequency` | How often the consignor receives a statement: `Weekly`, `Monthly` or `Quarterly`. |

## What happens to the unsold units

| Policy | When the units are due | What then |
|---|---|---|
| `ReturnToConsignor` | At the end of the term | They are given back. |
| `Extend` | At the end of the term and every extension | They are given back. |
| `BuyOut` | At the end of the term | The shop buys them, at the cost agreed. |

`UnsoldStock::due()` lists the units due, with what to do with them: see [Unsold units](unsold-units.md). The bundle
lists them; it never gives back or buys a unit by itself.

## Versions

An agreement is never changed. A new version applies to the units received from its instant on; the units received
under the previous version keep its term and its policy. Each version is checked against the past:

- the first version of a consignor may start at any instant, the past included, so that it covers the units already in
  the shop;
- each next version starts after the last one, and never before the instant it is written.

`ConsignorAgreements::agreementAt()` gives the version in effect at an instant — for units received then —, or `null`
before the first. Units received before a consignor's first agreement, or under one without a term, are never due.

The terms of each receipt line stay those written at the receipt: a new agreement changes neither a share nor a
commission. A consignor who changes their rates does so on their next receipt.

## Statement frequency

`ConsignorStatements::lastPeriod()` gives the last whole period over at an instant, at the frequency of the agreement
in effect then — monthly without one —, in UTC: the week from Monday, the calendar month, the calendar quarter. See
[Statements](statements.md).
