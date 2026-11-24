# Ratings

The ratings of each subject are computed from its published reviews and kept in `phil_review_subject`, so that a page,
a list of products or a ranking reads them without counting the reviews. `Phil\ReviewsBundle\Application\Ratings`
reads and computes them; `RatingSummary` holds them.

| Part of `RatingSummary` | What it is |
|---|---|
| `count`, `sum`, `mean()` | How many published reviews, the sum of their overall scores, and their plain average. |
| `weighted` | The weighted average under the prior of the type, or `null` without a review. |
| `criteria` | The average of each criterion over the reviews that rated it, with how many did (`CriterionRating`). |
| `superlatives` | How often each superlative was given. |
| `foreign`, `allFromSite()` | How many published reviews were not collected on the site. |
| `suspended` | Whether new reviews of the subject are suspended. |

## The weighted average

A subject with two perfect reviews should not rank above one with two hundred good ones. The weighted average adds
`prior.count` fictitious reviews of the neutral value `prior.mean` — the middle of the scale by default — to the real
ones:

```text
weighted = (mean × count + sum of the scores) / (count + number of reviews)
```

With the defaults, five fictitious reviews of 3 out of 5: a subject with two reviews of 5 has a weighted average of
(15 + 10) / 7 ≈ 3.57; one with forty reviews averaging 4.5 has (15 + 180) / 45 ≈ 4.33, and ranks first.

Its parameters are published: the summary of every page says how many fictitious reviews of which value are added,
and from how many reviews an average is shown (`prior.minimum`). Below that minimum, the page says how many reviews the
subject has, and shows no average.

## Computing them again

Every change of a review that touches the ratings — written, changed, withdrawn, published again, erased — computes
the ratings of its subject again, from the reviews themselves rather than by adding to the previous figures, so that a
computation repeated or interrupted leaves them right.

A computation first takes the row of its subject — an update the database serialises —, then reads the reviews and
writes the ratings in the same transaction. Two computations of one subject at the same moment run one after the
other, and the second reads what the first could not see: the last ratings written never miss a review written before
they were computed.

After a change of the prior or of the minimum of a type, compute every subject again:

```sh
php bin/console phil:reviews:recompute          # every declared type
php bin/console phil:reviews:recompute game     # one type
```

## Superlatives

The summary shows the `superlatives_shown` labels given most often, in decreasing order; a tie keeps the order of the
labels in the configuration:

```php
$summary->topSuperlatives($type->superlatives, $type->superlativesShown); // ['quick-to-teach' => 12, 'gorgeous' => 7]
```

## Ranking

```php
$ratings->ranking('game', 10); // the ten subjects of the type with reviews, by decreasing weighted average
```

A tie is broken by the number of reviews, then by identifier.

## Structured data

With `phil/structured-data` installed, `AggregateRatings` gives the schema.org `AggregateRating` of a subject, for the
application to set as the `aggregateRating` of the node of its product, game or place:

```php
$node = $aggregateRatings->of($subject); // ?Phil\StructuredData\Node

if (null !== $node) {
    $collector->add(Product::of('Catan', id: $url.'#product', properties: ['aggregateRating' => $node]));
}
```

```twig
{% set rating = phil_reviews_aggregate_rating('game', game.id) %}
```

It gives `ratingValue` — the plain average, rounded to two decimals —, `bestRating`, `worstRating` and `ratingCount`.
There is none:

- without a published review, or below the minimum of the type;
- when a published review of the subject was not collected on the site: a review the application brought from
  another system of its own carries `Origin::Transferred`, and the subject then has no structured data at all. The
  bundle never fetches reviews from another platform.
