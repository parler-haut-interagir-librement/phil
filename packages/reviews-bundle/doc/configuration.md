# Configuration

The bundle's configuration lives under `phil_reviews`:

```yaml
# config/packages/phil_reviews.yaml
phil_reviews:
    connection: default
    current_reviewer: ~             # Symfony Security's user identifier
    participations: App\Reviews\OrderParticipations
    responders: ~                   # Symfony Security and responder_attribute
    responder_attribute: ROLE_REVIEWS_RESPONDER
    reviewers: App\Reviews\MemberNames
    subjects: App\Reviews\CatalogueSubjects
    layout: 'base.html.twig'
    kit_subdirectory: ~             # the subdirectory of ux_toolkit
    page_size: 10
    erasure: anonymise              # or remove
    removal_criteria: [spam, off-topic, hate-or-harassment, personal-information, conflict-of-interest, not-a-participant, illegal]
    spam_protection: true
    rate_limit:
        write: [reviews]
        vote: [{ limiter: votes, key: user }]
    types:
        game:
            scale: 5
            criteria: [rules, replayability]
            superlatives: [great-for-beginners, gorgeous, quick-to-teach]
            max_superlatives: 3
            superlatives_shown: 3
            participants_only: false
            window: 30
            prior: { count: 5, mean: ~, minimum: 3 }
            body_length: 5000
        event:
            participants_only: true
            window: 14
```

## Options

| Option | Default | What it does |
|---|---|---|
| `connection` | `default` | The DBAL connection of the tables. |
| `current_reviewer` | `null` | The `CurrentReviewer` service naming who is signed in; Symfony Security's user identifier when null. An identifier with `@` is refused: give your own service when users sign in with their email address. |
| `participations` | `null` | The `Participations` service of the commerce or the ticketing; none when null, so no review is marked verified and a type reserved to its participants takes no review. |
| `responders` | `null` | The `ResponderPolicy` service saying who responds to the reviews of a subject; Symfony Security when null. |
| `responder_attribute` | `ROLE_REVIEWS_RESPONDER` | The attribute Symfony Security grants to those who respond, the `SubjectRef` given to the voters. |
| `reviewers` | `null` | The `ReviewerDirectory` service naming the authors; their identifiers when null. |
| `subjects` | `null` | The `SubjectDirectory` service naming the subjects and giving their addresses; their type and identifier when null. |
| `layout` | `@PhilReviews/layout.html.twig` | The template the pages extend, with the blocks `title` and `main`. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are under the components directory; the subdirectory of `ux_toolkit` when null. |
| `page_size` | `10` | How many reviews a page shows, 1 to 100. |
| `erasure` | `anonymise` | What the erasure of a person does to their reviews: `anonymise` or `remove` ([Privacy](privacy.md)). |
| `removal_criteria` | seven codes | The published criteria under which a review is withdrawn, at least one ([Moderation](moderation.md)). |
| `spam_protection` | `true` | Holds the review form to `phil/spam-protection-bundle` when it is registered. |
| `rate_limit.write` | `[]` | The rules of `phil/rate-limit-bundle` of the review form. |
| `rate_limit.vote` | `[]` | The rules of the votes, the responses and the contests. |
| `types` | `{}` | The types of subject, by name. A review of a type not declared is refused. |

A rule of `rate_limit` is a limiter's name, or a map of `limiter`, `amount` and `key`, as `phil/rate-limit-bundle`
reads its `_rate_limit` route default.

## A type of subject

A type is named by a lowercase letter, then up to 63 lowercase letters, digits, `.`, `-` and `_`: `product`, `game`,
`place`, `board.game`.

| Option | Default | What it does |
|---|---|---|
| `scale` | `5` | How many stars, 2 to 10. |
| `criteria` | `[]` | The codes of the criteria a review may rate, in the order of the form, 20 at most. |
| `superlatives` | `[]` | The codes of the positive labels a review may give, 64 at most. |
| `max_superlatives` | `3` | How many superlatives a review gives, 0 to 3. |
| `superlatives_shown` | `3` | How many of the most frequent superlatives the summary shows. |
| `participants_only` | `false` | Whether only the participants `Participations` knows review a subject of the type. |
| `window` | `30` | How many days after their participation a participant reviews; `null` for no limit. Read only with `participants_only`. |
| `prior.count` | `5` | How many fictitious reviews the weighted average adds, 0 to 10 000. |
| `prior.mean` | `null` | Their value; the middle of the scale when null — 3 out of 5. |
| `prior.minimum` | `3` | How many reviews before an average is shown, and the structured data given. |
| `body_length` | `5000` | How many characters the text of a review holds, 1 to 20 000. |

A code — of a criterion, a superlative or a removal criterion — is a lowercase letter, then up to 63 lowercase
letters, digits, `-` and `_`. Its label is translated in the domain `phil_reviews`, under `criteria.<code>`,
`superlatives.<code>` and `criterion.<code>`.

Changing the prior of a type changes the weighted averages already kept: compute them again with
`php bin/console phil:reviews:recompute`.
