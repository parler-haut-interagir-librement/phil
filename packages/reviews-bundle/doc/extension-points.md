# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports the application implements

| Port | Default | What it says |
|---|---|---|
| `Application\Contract\CurrentReviewer` | `SecurityCurrentReviewer`, the user identifier of Symfony Security | Who is signed in: `member(): ?MemberId`. |
| `Application\Contract\Participations` | `NoParticipations` | A member's part in a subject: `of(SubjectRef, MemberId): ?Participation`, from the commerce or the ticketing. |
| `Application\Contract\ResponderPolicy` | `RoleResponders`, the attribute `responder_attribute` | Who responds to the reviews of a subject: `mayRespond(SubjectRef, MemberId): bool`. |
| `Application\Contract\SubjectDirectory` | `IdentifierDirectory` | The name of a subject and the address of its page. |
| `Application\Contract\ReviewerDirectory` | `IdentifierDirectory` | The name of an author. |

Name the service in the configuration (`current_reviewer`, `participations`, `responders`, `subjects`, `reviewers`),
or alias the interface to it in `config/services.yaml`.

## Stores

`Domain\Contract\ReviewRepository` and `Domain\Contract\RatingRepository` are aliased to the DBAL stores. A store of
your own keeps their contracts: a second review of a subject by its author throws `DuplicateReview` from a unique
constraint of the store, a second vote replaces the first, the counts of the votes are written from the votes only,
and a computation of the ratings is idempotent and serialised on its subject.

## Events

| Event | When |
|---|---|
| `Application\Event\ReviewWritten` | A review was written or changed. |
| `Application\Event\ReviewWithdrawn` | A review was withdrawn, or held while the moderation looks at it. |
| `Application\Event\ReviewPublished` | A withdrawn review was published again. |
| `Application\Event\ContestFiled` | An author contested a withdrawal. |

Each carries the identifiers of the review and of its subject, never its text: a listener that needs more reads the
review from `Reviews::get()`. A Messenger message built from an event carries the same identifiers.

## Integrations

| To | Do |
|---|---|
| Report reviews | Register `phil/moderation-bundle`; the target `review` and its listener are registered with it. |
| Export and erase | Install `phil/privacy-bundle`; `ReviewPrivacy` is tagged `phil_privacy.exporter` and `phil_privacy.eraser`. |
| Give structured data | Install `phil/structured-data`; `AggregateRatings` is registered. |
| Protect the form | Register `phil/spam-protection-bundle`; `spam_protection: false` leaves the form without it. |
| Limit the forms | Declare limiters in `phil/rate-limit-bundle` and name them under `rate_limit`. |
| Change a page | Override the template under `templates/bundles/PhilReviewsBundle/`. |
| Change a label | Translate the key in the domain `phil_reviews`. |
