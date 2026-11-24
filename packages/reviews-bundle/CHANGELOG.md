# Changelog

All notable changes to `phil/reviews-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `PhilReviewsBundle` and its configuration `phil_reviews`: the connection, the ports, the layout, the size of a page,
  the erasure, the removal criteria, the spam protection, the rate limits, and the types of subject with their scale,
  criteria, superlatives, participants, window, prior and length of text.
- The reviews (`Domain\Model\Review`): an overall score and the scores of the criteria of the type, a title, a text,
  up to three superlatives, the compensation received, the mark of a verified purchase or attendance, the origin, the
  state of moderation, the public response and the contest of a withdrawal; their values (`SubjectRef`, `MemberId`,
  `ReviewId`, `ReviewContent`, `Compensation`, `Response`, `Contest`, `Participation`).
- `Application\Reviews`: one review per member and subject, its edition, the votes of helpfulness — one per member,
  changed or taken back —, the responses of the members `ResponderPolicy` admits, the withdrawal under a published
  criterion, the review held while the moderation looks at it, its publication again, the contest of a withdrawal
  and its outcome, the reviews reserved to the participants within the window of their type, and the reasons a member
  may not review a subject.
- `Application\Ratings` and `Domain\ValueObject\RatingSummary`: the count, the sum, the weighted average under the
  published prior of the type (`BayesianPrior`), the average of each criterion, the superlatives given most often, the
  reviews not collected on the site and the suspension of a subject; computed again after each change, idempotent and
  serialised on the subject; the ranking of a type by weighted average.
- `Application\RemovalCriteria`: the published criteria under which a review is withdrawn, and the criterion a public
  reason of the moderation names.
- The ports `CurrentReviewer`, `Participations`, `ResponderPolicy`, `SubjectDirectory` and `ReviewerDirectory`, with
  their defaults: Symfony Security's user, no participation, Symfony Security's attribute, and the identifiers.
- The events `ReviewWritten`, `ReviewWithdrawn`, `ReviewPublished` and `ContestFiled`, which carry identifiers only.
- The DBAL stores `DbalReviewRepository` and `DbalRatingRepository`, their tables (`ReviewTables`), moments in
  `datetime_utc_immutable`, and `SchemaListener`, which adds them to the schema the ORM generates.
- `ReviewTarget` and `ModerationDecisionListener`: the reviews reported in the queue of `phil/moderation-bundle`, a
  decision that hides or removes one withdrawn under the criterion its public reason names, or published again.
- `ReviewPrivacy`: the reviews, votes and responses of a member in the export of `phil/privacy-bundle`, and their
  erasure — the reviews anonymised or removed, as the configuration says, and the ratings computed again.
- `AggregateRatings` and the Twig function `phil_reviews_aggregate_rating()`: the schema.org `AggregateRating` of a
  subject through `phil/structured-data`, only when every published review was collected on the site.
- The pages (`ReviewsController`, `config/routes.php`): the summary of the ratings, the reviews in four orders and by
  page, the forms of the review, the votes, the responses and the contests, without JavaScript, under the stateless
  CSRF check; `SpamGuard`, the review form held to `phil/spam-protection-bundle`; `RateLimitRoutes`, the rules of
  `phil/rate-limit-bundle` given to the forms.
- The templates, drawn with the recipes `Rating`, `Button`, `Badge`, `Input`, `Checkbox` and `Textarea` of the kit,
  the Twig functions `phil_reviews_kit()`, `phil_reviews_name()`, `phil_reviews_summary()` and `phil_reviews_type()`,
  and the translations in English and French.
- The Stimulus controller `helpful`, which sends a vote in the background and writes the counts again.
- The command `phil:reviews:recompute`.
- The Flex recipe in `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
