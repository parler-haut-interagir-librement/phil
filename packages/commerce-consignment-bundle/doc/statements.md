# Statements

A **statement** tells a consignor what happened to their units over a period: what sold, what customers brought back,
what the shop bought from them and what it gave back, what they are owed and what the shop kept, and what is still in
the shop, with its age.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\Money\Currency;

$author = Owner::consignor('author-17');
$statement = $statements->issue($author, Currency::of('CAD'), $statements->lastPeriod($author, $clock->now()), $clock->now());
$entityManager->flush();

$statement->number;         // ST-00000042
$statement->share();        // what the shop owes the author for the period
$statement->commission();   // what it kept
$statement->lines();        // the entries of the period
$statement->held();         // the units still in the shop at the end of the period, with their age
```

## The period

A period is a `StatementPeriod`: two instants in UTC, the start included and the end excluded. An entry written at the
last microsecond of October is in October's statement; one written at midnight on the first of November is in
November's. A period lasts at most 367 days, and a statement covers only a period that is over: one that ends after
the instant of issue is refused, so that what happens later in the period is not left out of it.

`StatementFrequency::lastClosedPeriod()` and `ConsignorStatements::lastPeriod()` give the last week, month or quarter
over at an instant; any other period is built with `StatementPeriod::of()`.

## What a statement holds

A statement is read from the entries of the ledger, never worked out again from the sales:

| Line | Units | Revenue | Share | Commission |
|---|---|---|---|---|
| A sale (`sold`) | the units sold | the price paid | the consignor's share | the shop's commission |
| A customer's return (`returned`) | negative | negative | negative | negative |
| A buy-out (`bought_out`) | the units the shop bought | the agreed cost | the agreed cost | zero |
| Units given back (`given_back`) | the units given back | zero | zero | zero |

A customer's return lowers what the consignor is owed in the period it is written, even when the sale was in an
earlier period: the earlier statement stays as it was, and this one carries the difference. The totals are the sums of
the lines; the share and the commission add up to the revenue, line by line and in total, and a statement whose sums
would not is refused: no cent is lost or created.

A statement is in one currency: the sales, returns and buy-outs in another currency go to the statement of that
currency, issued separately. The units given back, which carry no money, are on every statement of the period.

The units still held are summed from the entries before the end of the period, layer by layer, oldest receipt first,
each with its SKU, location, receipt instant and age in whole days at the end of the period.

A statement holds at most 5 000 lines and the units of 1 000 layers: a longer period is refused, and issued in shorter
ones.

## Numbers

Each statement has a number of its own series, given by the `StatementNumbering` port when it is issued.
`DoctrineStatementNumbering`, the default, counts in the statements' table: the configured prefix, then eight digits,
one more than the highest number of that prefix — `ST-00000001`, `ST-00000002`. Two requests that take the same number
at once cannot both flush: the unique constraint on the number refuses the second, which issues its statement again.
A series of its own — per year, per shop — is an adapter of the application: see
[Extension points](extension-points.md).

## Issued once, never changed

A statement is frozen when it is issued: its figures and its lines are copied into its row, and no later entry, no
new agreement, changes them. A statement issued again for the same period is a new one, with a new number; the first
stays. `ConsignorRepository::statement()` finds one by its number, and `statementsOf()` lists a consignor's
statements, the last issued first.

## CSV

`StatementCsv::write()` gives a statement as CSV: a header, one row per line, then a row of totals, amounts in decimal.
A text cell a spreadsheet would read as a formula — starting with `=`, `+`, `-` or `@` — starts with an apostrophe.

```php
return new Response($statementCsv->write($statement), headers: [
    'Content-Type' => 'text/csv; charset=UTF-8',
    'Content-Disposition' => sprintf('attachment; filename="%s.csv"', $statement->number),
]);
```

## A page or a document

`StatementView::data()` gives a statement as plain data — strings and integers, amounts in decimal, instants in UTC —
for a template. The bundle ships one, `@PhilCommerceConsignment/statement.html.twig`, accessible and unstyled, which an
application overrides in `templates/bundles/PhilCommerceConsignmentBundle/statement.html.twig`:

```php
return $this->render(StatementView::TEMPLATE, ['statement' => $statementView->data($statement)]);
```

The HTML it renders can be printed to PDF by any HTML-to-PDF converter the application uses.

The application decides who reads a statement: the bundle neither sends it nor checks who asks for it.
