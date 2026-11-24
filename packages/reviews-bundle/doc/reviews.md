# Reviews

`Phil\ReviewsBundle\Application\Reviews` is the entry point. Every method takes identifiers and values, never a
request: the pages call it, and so can an application's own controllers, commands or API.

## A subject

A subject is named, never held: a type the configuration declares and the identifier the application gives it.

```php
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

$subject = SubjectRef::of('game', 'catan');
```

The identifier is 1 to 190 bytes of visible characters. `SubjectDirectory` gives the pages its name and the address
of its own page.

## Writing a review

```php
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;

$review = $reviews->write(
    $subject,
    MemberId::fromString('m-1842'),
    new ReviewContent(4, ['rules' => 5, 'replayability' => 3], 'Easy to teach', 'We played it four times in a row.', ['quick-to-teach']),
    Compensation::received('A copy from the publisher'),
);
```

| Part | Rule |
|---|---|
| Overall score | Required, from 1 to the scale of the type. |
| Criteria | The scores of the criteria of the type the member chose to rate; a criterion left unrated is left out. |
| Title | Optional, 150 characters at most. |
| Text | Optional, the `body_length` of the type at most. |
| Superlatives | Up to `max_superlatives` of the labels of the type, never more than three. |
| Compensation | Whether the author received something in exchange, and what, 200 characters at most. |

A text is trimmed, valid UTF-8, without control characters but line breaks and tabs. A member identifier is 1 to 190
bytes and never an email address: an identifier kept beside every review must not be a way to reach the person.

`write()` refuses, with a `ReviewRefused` whose message can be shown:

- a second review of the same subject by the same member: they change theirs instead. The unique constraint of
  `phil_review` decides, so that two requests at the same moment cannot both write one;
- a review while the reviews of the subject are suspended;
- where only participants review, a member who took no part, a participation to come, or one older than the window.

A content that does not fit the type throws `InvalidReview`.

The review is published as it is written, and the ratings of the subject are computed again ([Ratings](ratings.md)).
`ReviewWritten` is dispatched with the identifiers of the review and the subject.

## Changing a review

```php
$reviews->edit($review->id, $author, new ReviewContent(5), Compensation::none());
```

Only the author changes a published review; its edition is dated and the page says « edited ».

## Participants and the verified mark

`Participations` is the port the commerce or the ticketing of the application implements:

```php
final class OrderParticipations implements Participations
{
    public function of(SubjectRef $subject, MemberId $member): ?Participation
    {
        $delivered = $this->orders->lastDeliveryOf($member->value, $subject->id);

        return null === $delivered ? null : new Participation(Verification::Purchase, $delivered);
    }
}
```

The moment of a participation is when the window opens: the delivery, the end of the game or the event. A review is
marked « verified purchase » or « took part » whenever the port knows of a participation, whatever the type. For a
type with `participants_only`, a member reviews from that moment until `window` days after it, the last second
included; `window: null` lifts the limit.

## Compensations

A review given against a compensation — a free product, a discount, a payment, a free seat — says so wherever it is
shown, with what was received in the author's words. The form asks it of every author, and the mention cannot be
hidden by a template option.

## Votes of helpfulness

```php
$reviews->vote($review->id, $voter, true);   // helpful
$reviews->vote($review->id, $voter, false);  // not helpful: replaces the first vote
$reviews->retractVote($review->id, $voter);
```

A member votes once on a review, never on their own, and only on a published one. The primary key of
`phil_review_vote` decides between two votes at the same moment; the counts of the review are written again from the
votes by one statement, so that they never drift. A review saved after a vote keeps the counts of the votes.

## Responses

```php
$reviews->respond($review->id, $merchant, 'Thank you — a new box is on its way.');
$reviews->removeResponse($review->id, $merchant);
```

One public response per review, shown under it; a second one replaces the first. `ResponderPolicy` says who
responds: by default the members Symfony Security grants `responder_attribute`, the subject given to the voters, so
that a voter of the application can admit the merchant of each product or the organiser of each event.

## Suspending a subject

```php
$ratings->suspend($subject, true);   // no new review; those given stay and still count
$ratings->suspend($subject, false);
```

## Reading

| Method | What it gives |
|---|---|
| `of($subject, $order, $limit, $offset)` | The published reviews, by `Helpful`, `Newest`, `Highest` or `Lowest`. Every order lists every published review. |
| `count($subject)` | How many published reviews. |
| `get($id)` | A review, whatever its state. |
| `mineOf($subject, $member)` | The member's review of the subject, whatever its state. |
| `voteOf($id, $member)` | The member's vote: `true`, `false` or `null`. |
| `mayRespond($subject, $member)` | Whether the member responds to the reviews of the subject. |
| `refusal($subject, $member)` | Why the member may not review now — `suspended`, `window` or `unknown` —, or `null`. |
