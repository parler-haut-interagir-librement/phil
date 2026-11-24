# Moderation

Every review is published as it is written, the negative ones as the positive ones. None is filtered out of a list,
and no order hides any. A review is withdrawn only under one of the published criteria, and its author contests the
withdrawal once, with their evidence.

## The published criteria

`removal_criteria` lists the criteria, in the order the page of the rules shows them. The defaults:

| Code | When a review breaks it |
|---|---|
| `spam` | Advertising, links or text that do not review the subject. |
| `off-topic` | A review of something else: the courier, another product, a dispute elsewhere. |
| `hate-or-harassment` | Insults, threats, or attacks on a person or a group. |
| `personal-information` | The address, phone number or other personal information of someone. |
| `conflict-of-interest` | A review by the merchant, the organiser, their staff or a competitor. |
| `not-a-participant` | A review of a subject the author did not buy or take part in, where only participants review. |
| `illegal` | Content the law forbids. |

Every page of reviews shows them under « How reviews are moderated », with their explanations, translated in the
domain `phil_reviews` under `criterion.<code>` and `criterion.<code>.help`. A site that publishes other criteria
lists its own codes and translates them.

```php
$reviews->withdraw($review->id, 'off-topic');   // ReviewRefused for a code that is not published
```

A withdrawn review leaves the lists and the ratings, keeps its criterion and the moment of its withdrawal, and its
author still sees it, with the criterion, on the page of the subject. `ReviewWithdrawn` is dispatched.

## Reports through phil/moderation-bundle

With `phil/moderation-bundle` registered, the reviews are a target of its one queue, of type `review`: a member reports
a review there, with a reason of its catalogue, and the queue shows the review with its subject, excerpt and author.

| Decision of the moderator | What happens to the review |
|---|---|
| `Hide`, `Remove` | Held out of sight, then withdrawn under the criterion the public reason of the decision names. |
| `Restore`, `Approve` | Published again. |
| `Dismiss`, `Warn` | Nothing. |

The public reason names a criterion by its code, alone or followed by `:` or a space and an explanation:
`off-topic: about the courier`. A decision whose public reason names no published criterion publishes the review
again, and the refusal is logged at warning level with the review and the decision: no review is withdrawn outside the
criteria, even by the moderation. A review hidden by trusted members, before a decision, stays held — out of sight,
without a criterion — until the moderator decides.

## Contests

```php
$reviews->contest($review->id, $author, 'My review is about the game itself: the rules are in the box.');
$reviews->settleContest($review->id, upheld: true);  // the review is published again
$reviews->settleContest($review->id, upheld: false); // the withdrawal stands
```

The author of a review withdrawn under a criterion contests it once per withdrawal, from the page of the subject, with
their evidence, 5 000 characters at most. `ContestFiled` is dispatched for the application to bring it before
whoever decides; its outcome is kept, and the page tells the author.

## Spam protection and rate limits

With `phil/spam-protection-bundle` registered and `spam_protection` on, the review form carries its honeypot, its
signed single-use token and its proof of work, and the title and text go through its content rules and rate limit. A
submission it refuses is told « This submission could not be accepted », never why.

With `phil/rate-limit-bundle`, the rules of `rate_limit` are given to the forms: `write` to the review form, `vote` to
the votes, the responses and the contests. A form refused by a limiter is answered `429 Too Many Requests` by that
bundle.

```yaml
phil_rate_limit:
    limiters:
        reviews: { policy: sliding_window, limit: 5, interval: '1 hour', key: user_or_ip }
        votes: { policy: token_bucket, limit: 30, rate: { interval: '1 minute', amount: 10 } }

phil_reviews:
    rate_limit:
        write: [reviews]
        vote: [{ limiter: votes, key: user }]
```
