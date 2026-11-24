# Privacy

The text of a review is personal data, and so are its author, the votes a member gave and the responses they wrote.
With `phil/privacy-bundle` installed, `ReviewPrivacy` is its module `reviews`, the data subject being the member's
identifier; a data subject that cannot be a member identifier — an email address — holds nothing here.

## The export

The export gives, for the member:

- `reviews`: every review they wrote, whatever its state — subject, scores, title, text, superlatives, compensation,
  verified mark, state, removal criterion, moments, and the contest of a withdrawal;
- `votes`: the reviews they voted on, how, and when;
- `responses`: the responses they wrote, and when.

## The erasure

The erasure is idempotent: run again, it finds nothing left and reports the same effects.

| Store | `erasure: anonymise` (default) | `erasure: remove` |
|---|---|---|
| `phil_review` | The author, title, text, contest and words of the compensation removed; the scores, superlatives, mark of the compensation, subject and moment kept. | The reviews deleted. |
| `phil_review_score`, `phil_review_superlative` | Kept with the anonymised review. | Deleted with the review. |
| `phil_review_vote` | Deleted; the counts of the reviews written again. | Deleted; the counts written again. |
| Responses | Removed from the reviews they answered. | Removed. |

The report names the stores `Anonymised` or `Deleted`. `anonymise` keeps the ratings of the subjects as the
reviews made them, which the site judges non-identifiable once nothing but scores and a date are left; a site where a
subject has so few reviews that a score and a date single a person out chooses `remove`. Either way the ratings of
every subject the erasure touched are computed again.

An anonymised review shows « A former member » as its author, and can no longer be changed or contested.
