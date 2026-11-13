# The cross-agreement rule

A majority of raters says what most of them think; a community note should say what people who usually think
differently both find helpful. The rule looks for that agreement across camps, pair by pair, and keeps the evidence.

## How a note is decided

`CrossAgreementRule::decide()` takes the ratings of a note and the history of its raters, and goes through four steps.

1. **Enough ratings.** With fewer than `minRatings`, the note needs more ratings: `too_few_ratings`.
2. **A clear verdict.** The verdict holding at least the share `share` of the ratings leads; when neither does, the
   note needs more ratings: `no_clear_majority`.
3. **Pairs of raters who usually disagree.** Among the raters of the leading verdict, the earliest first, the rule looks
   for pairs who usually disagree. Two raters usually disagree when, on the other notes they both rated — at least
   `minShared` of them —, they gave the same verdict on less than the share `divergence`. Each rater is in one pair at
   most, so that no single rater carries a decision; the rule stops once it has `pairs` pairs.
4. **The decision.** With `pairs` pairs, the note gets the leading verdict, `helpful` or `not_helpful`:
   `cross_agreement`. With fewer, it needs more ratings: `no_cross_agreement`.

The note being decided is left out of the history, so that a note cannot prove itself.

## The numbers

| Argument | Default | What it is |
|---|---|---|
| `minRatings` | 5 | The ratings a note needs before anything is decided, 1 or more. |
| `share` | `[2, 3]` | The share of the ratings the leading verdict holds at least, above one half and at most all of them. |
| `pairs` | 2 | The pairs of raters who usually disagree a decision needs, 1 or more. |
| `minShared` | 3 | The other notes two raters must share before their history says anything, 1 or more. |
| `divergence` | `[1, 2]` | Two raters usually disagree when they agreed on less than this share of the notes they shared. |
| `maxRaters` | 50 | The raters of the leading verdict looked at, the earliest first, from twice `pairs` to 200. |

Shares are written as a numerator and a denominator, and every comparison is made in integers: `agreed × 2 < shared`
for `[1, 2]`. A rule out of these bounds throws `InvalidArgumentException`.

A small community starts with the defaults, or `pairs: 1` while few members rate; a large one raises `pairs` and
`minRatings`.

## An example

Ann and Bob usually find the same notes helpful; so do Cid and Dan; the two pairs disagreed on the three notes they
all rated. A note of Zoe's gets five helpful ratings, from Ann, Bob, Cid, Dan and Eve, in that order. Five ratings, all
helpful: the verdict leads. The earliest raters are paired first: Ann and Bob agree too often; Ann and Cid disagreed on
3 of 3 shared notes — a pair; Bob and Dan likewise — a second pair. The note is helpful, and its decision says so:

```php
[
    'status' => 'helpful',
    'reason' => 'cross_agreement',
    'helpful' => 5,
    'not_helpful' => 0,
    'verdict' => 'helpful',
    'bridges' => [
        ['first' => 'user:ann', 'second' => 'user:cid', 'shared' => 3, 'agreed' => 0],
        ['first' => 'user:bob', 'second' => 'user:dan', 'shared' => 3, 'agreed' => 0],
    ],
    'rule' => ['min_ratings' => 5, 'share' => '2/3', 'pairs' => 2, 'min_shared' => 3, 'divergence' => '1/2', 'max_raters' => 50],
]
```

Had only Ann and Bob rated it, with Eve and two others of their camp, no pair would disagree: the note would wait,
`no_cross_agreement`, however many of one camp rated it.

## The explanation

`Decision` holds everything that explains a note's status: the status, the reason, the ratings of each verdict, the
verdict looked at, the pairs — each a `Bridge` of two raters with their `PairHistory` — and the rule itself. It is
kept with the note, so that the explanation is the one of the decision, not of a rule changed since.

`toArray()` gives it as plain values, with the raters' references, for the moderators and an audit; `toPublicArray()`
leaves the references out and keeps, for each pair, the notes shared and those agreed on, for the readers. The reason is
a stable code, `DecisionReason`, that the application translates.

## Cost

The rule asks the history of at most `maxRaters × (maxRaters − 1) ÷ 2` pairs: 1225 with the defaults, when no pair is
found. With fifty raters over a history of ten thousand ratings kept in memory, a decision takes about 0.3 ms when
two pairs are found at once, 1.2 ms for ten pairs, and about 110 ms in the worst case, every pair asked. A store in a
database answers `between()` from an index on the rater and the note, or lowers `maxRaters`.

## What the rule is not

The rule does not compute a hidden score of a rater, nor sort raters into camps once and for all: the camps are seen pair
by pair, from what the raters did, and the decision names the pairs it found. It does not learn from thousands of
ratings either; a community that grows to that size may put a model of its own behind the same `Decision`.
