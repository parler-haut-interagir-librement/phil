# The journal

## What is recorded

Each task that reached the model is one row of `phil_ai_journal`:

| Column | Meaning |
|---|---|
| `id` | The task's identifier, a UUID v7, also the suggestion's |
| `kind` | The task kind |
| `output` | What it asked for: `text`, `structured`, `image` or `speech` |
| `subject` | Whom the task was charged to |
| `platform`, `model` | Where it ran |
| `key_source` | `application` or `user` |
| `outcome` | `succeeded` or `failed` |
| `input_tokens`, `output_tokens` | The tokens the platform counted; 0 for a failed task |
| `cost_micros`, `currency` | The cost from the price list |
| `started_at`, `duration_ms` | When the call started, and how long it took |
| `decision`, `decided_at` | `pending`, `accepted`, `edited` or `rejected`, and when |

A task refused before its call — unknown kind, missing prompt, no key, quota reached — is not journaled.

## What is never recorded

The prompt, its parameters, the answer, the person's key, the owner: none of it reaches the journal, nor any exception
message of the bundle. What a person wrote and what a model proposed belong to the application, which keeps them
with the text they apply to, or nowhere.

## Costs

The price list gives each model its price in micros of the configured currency per million tokens read and per million
written, and per call for a model that charges by the image or the request; a task's cost is rounded up to the next
micro. A model without a price costs 0 — useful for a local model.
The journal keeps the currency of each row, and totals add costs by currency.

## Totals

```php
$totals = $tasks->totals('project-42', new DateTimeImmutable('first day of this month midnight'), new DateTimeImmutable('first day of next month midnight'));

$totals->tasks;        // tasks journaled in the span
$totals->failed;       // of which failed
$totals->tokens;       // Tokens: input and output
$totals->costs;        // ['USD' => 1234567], micros by currency
$totals->decisions;    // ['pending' => 1, 'accepted' => 7, 'edited' => 3, 'rejected' => 2]
```

The span runs from its start included to its end excluded. The journal's store sums them: the Doctrine journal runs one
query grouped by outcome, decision and currency over the index on subject and start, whatever the number of rows, and
the journal in memory adds its entries.

## Erasure

```php
$tasks->erase('project-42');   // how many rows were forgotten
```

When the account or project a subject names is erased, its journal goes with it. With `phil/privacy-bundle`, the
journal is its module `ai`, which exports and erases a data subject's rows ([Privacy requests](transparency.md#privacy-requests)).
