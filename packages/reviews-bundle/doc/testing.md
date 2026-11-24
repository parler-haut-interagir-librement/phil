# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

| Suite | What it covers |
|---|---|
| `tests/Unit/Domain` | The values and the review: their checks, its states, withdrawals, contests and anonymisation. |
| `tests/Unit/Application` | `Reviews` and `Ratings` over SQLite in memory: one review per member, the window of the participants to the second, the verified mark, the compensation, votes, responses, withdrawals, contests, orders and ranking; the types and the removal criteria. |
| `tests/Unit/Infrastructure` | The DBAL stores and their tables, two connections to one SQLite file writing one review, voting and computing at once, the moderation, the privacy, the `AggregateRating` absent when a review was not collected on the site, the spam guard, the rate limits, the command and the Twig functions. |
| `tests/Unit` | The configuration, the services registered with each optional package, and the Flex recipe. |
| `tests/Functional` | The pages in an application: the forms without JavaScript, the votes with and without JSON, the responses, the contests, the errors and the pages of reviews. |
| `tests/js` | The Stimulus controller of the votes, under Node's test runner. |

The unit suite runs without a kernel, a database server or a network. In the monorepo, Infection holds the package at
100 % of covered mutants.
