# Installation

```sh
composer require phil/reviews-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_reviews.yaml` with one type of
subject, and imports the pages under `/reviews`. Without it, register the bundle in `config/bundles.php`:

```php
Phil\ReviewsBundle\PhilReviewsBundle::class => ['all' => true],
```

declare at least one type of subject ([Configuration](configuration.md)), and import the pages:

```yaml
# config/routes/phil_reviews.yaml
phil_reviews:
    resource: '@PhilReviewsBundle/config/routes.php'
    prefix: /reviews
```

## The tables

The bundle keeps its reviews through Doctrine DBAL, on the connection `connection` names. With the ORM installed,
its tables are added to the schema the ORM generates for that connection, so that the migrations write them:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

| Table | What it holds |
|---|---|
| `phil_review` | One row per review, unique by subject and author. |
| `phil_review_score` | The score of each criterion a review rated. |
| `phil_review_superlative` | The superlatives a review gave. |
| `phil_review_vote` | The votes of helpfulness, unique by review and voter. |
| `phil_review_subject` | The ratings of each subject, and whether its reviews are suspended. |

## The pages

The pages need Twig, Symfony Routing and Symfony's CSRF protection, and draw the recipes `Rating`, `Button`, `Badge`,
`Input`, `Checkbox` and `Textarea` of the PHIL UX kit, installed with the Symfony UX Toolkit:

```sh
composer require symfony/twig-bundle symfony/ux-twig-component phil/ux-kit
php bin/console ux:install rating button badge input checkbox textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

With AssetMapper, the Stimulus controller of the votes is served without a build step.

## Optional packages

| Package | What it adds |
|---|---|
| `phil/moderation-bundle` | Reports in the one queue of the moderation, and withdrawals under the published criteria. |
| `phil/privacy-bundle` | The reviews, votes and responses in the export and the erasure of a member's data. |
| `phil/structured-data` | The schema.org `AggregateRating` of a subject. |
| `phil/spam-protection-bundle` | The honeypot, the single-use token, the proof of work and the content rules of the review form. |
| `phil/rate-limit-bundle` | The limits of the forms, by the rules `rate_limit` names. |
| `symfony/security-bundle` | The current member and those who respond, from the signed-in user. |
| `symfony/console` | `phil:reviews:recompute`. |

The package needs PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later.
