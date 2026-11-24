# phil/reviews-bundle

**Moderated reviews and ratings of a product, game or place, with multi-criteria reviews and helpfulness votes from
the first version.**

phil/reviews-bundle is a Symfony bundle. A member gives a subject an overall score, the scores of the criteria its
type declares, a text and up to three superlatives; the others vote on how helpful the review was; the merchant or the
organiser responds publicly. The ratings of each subject — a weighted average published with its parameters, the
average of each criterion, the superlatives given most often — are kept beside the reviews and computed again after
every change.

- One review per member and subject, and one vote per member and review, decided by the unique constraints of the
  tables.
- Reviews reserved to the participants within a window, 30 days by default, and the mark « verified purchase » or
  « took part », through a port the commerce or the ticketing implements.
- Every review is published, the negative ones as the positive ones: a review is withdrawn only under a published
  criterion, and its author contests the withdrawal once, with their evidence.
- A review given against a compensation always says so.
- The reviews of a subject are suspended and resumed without losing those given.
- Reported through `phil/moderation-bundle`, exported and erased through `phil/privacy-bundle`, given as a schema.org
  `AggregateRating` through `phil/structured-data` when every review was collected on the site, the form held to
  `phil/spam-protection-bundle` and `phil/rate-limit-bundle` when they are installed.
- Pages drawn with the recipes of the PHIL UX kit: stars read as text by screen readers, forms that work without
  JavaScript, and a Stimulus controller that sends the votes in the background.

## Status

| Part | State |
|---|---|
| Reviews, votes, responses, withdrawals and contests (`Reviews`) | Implemented and tested |
| Ratings, weighted average, ranking and `phil:reviews:recompute` (`Ratings`) | Implemented and tested |
| DBAL stores and their schema listener | Implemented and tested |
| Moderation, privacy, structured data, spam protection and rate limits | Implemented and tested |
| Pages, Twig functions and the Stimulus controller of the votes | Implemented and tested |
| Flex recipe (`recipe/0.1`) | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables and the pages. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Reviews](doc/reviews.md) | Writing and changing a review, votes, responses, participants, compensations, suspension. |
| [Ratings](doc/ratings.md) | The weighted average and its parameters, criteria, superlatives, ranking, `AggregateRating`. |
| [Moderation](doc/moderation.md) | The published criteria, reports, withdrawals, contests, spam protection and rate limits. |
| [Pages](doc/pages.md) | The routes, the templates, the recipes of the kit, accessibility and the Stimulus controller. |
| [Privacy](doc/privacy.md) | The export and the erasure of a member's reviews, votes and responses. |
| [Extension points](doc/extension-points.md) | The ports to implement, the services to replace, the events, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\ReviewsBundle\PhilReviewsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_reviews.yaml
phil_reviews:
    types:
        game:
            criteria: [rules, replayability]
            superlatives: [great-for-beginners, gorgeous, quick-to-teach]

# config/routes/phil_reviews.yaml
phil_reviews:
    resource: '@PhilReviewsBundle/config/routes.php'
    prefix: /reviews
```

The reviews of the game `catan` are then at `/reviews/game/catan`.

## Requirements

PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
