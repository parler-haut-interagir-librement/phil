# Pages

`config/routes.php` declares the pages under the prefix the application chooses:

| Route | Method and path | What it does |
|---|---|---|
| `phil_reviews_page` | `GET /{type}/{id}` | The reviews of a subject: its ratings, its reviews by page, the member's form, the rules. |
| `phil_reviews_write` | `POST /{type}/{id}` | Writes or changes the member's review. |
| `phil_reviews_vote` | `POST /review/{review}/vote` | Votes `helpful` or `unhelpful`, or takes the vote back. |
| `phil_reviews_respond` | `POST /review/{review}/response` | Publishes the response, or removes it when the text is empty. |
| `phil_reviews_contest` | `POST /review/{review}/contest` | Contests the withdrawal of the member's review. |

A type not declared, a malformed identifier or an unknown review is answered `404`. Every page is answered with
`Cache-Control: no-store, private`: it holds the member's own forms and votes.

The page takes the query parameters `order` — `helpful` (the default), `newest`, `highest` or `lowest` — and `page`.

## Plain forms

Every form is a plain HTML form, posted and answered by a redirection to the review on the page (`303 See Other`), so
that it works without JavaScript and a reload does not post it again. A form passes Symfony's stateless CSRF check of
the token `phil_reviews`, and is refused when the browser says it was sent from another site; it is then shown again,
`403`, with « This form expired or did not come from this site ». A review the rules refuse is shown again, `422`, with
the reason and what the member wrote.

A visitor reads the reviews and the rules; a signed-in member gets the form, or the state of their review when it was
withdrawn, and the contest form when it may be contested.

## Accessibility

- The stars are the recipe `Rating` of the kit. A score shown is one image whose text says it — « Overall score 4 out of
  5 stars » —, the stars themselves hidden from screen readers; a score chosen is a group of radio buttons with a
  legend, which the browser gives the keyboard. Nothing is said by colour alone.
- Every section has a heading, every form field a label, and an error is announced (`role="alert"`) and tied to its
  form by `aria-describedby`.
- The vote buttons say whether they are pressed (`aria-pressed`), and the counts are a live region.
- The mention of a compensation is a note (`role="note"`) beside the review it concerns.

## The recipes of the kit

The templates draw `Rating`, `Button`, `Badge`, `Input`, `Checkbox` and `Textarea`, under the components subdirectory
of the Symfony UX Toolkit, or `kit_subdirectory`:

```yaml
phil_reviews:
    kit_subdirectory: ui   # ui:Rating, ui:Button…
```

## The Stimulus controller

`phil--reviews-bundle--helpful` sends a vote in the background, asking for JSON, then writes the new counts and which
button is pressed. When the request fails or is refused, it sends the form as usual, and the page that comes back says
why. With AssetMapper the bundle declares its controllers directory; with Symfony UX's `assets/controllers.json`,
`assets/package.json` names it `helpful`.

The vote route answers a request that prefers JSON with `{"helpful": 3, "unhelpful": 1, "vote": true}`, or
`{"error": "…"}` with the status of the refusal.

## Templates

| Template | What it draws |
|---|---|
| `@PhilReviews/layout.html.twig` | The page around the reviews when the application names no `layout`. |
| `@PhilReviews/page.html.twig` | The page of a subject. |
| `@PhilReviews/_summary.html.twig` | The ratings of a subject: average, criteria, superlatives, the method of the weighted average. |
| `@PhilReviews/_review.html.twig` | One review, its votes and its response. |
| `@PhilReviews/_form.html.twig` | The form of the member's review. |

A template is overridden under `templates/bundles/PhilReviewsBundle/`, and a label by translating its key in the
domain `phil_reviews`. The summary can be drawn on the application's own pages:

```twig
{{ include('@PhilReviews/_summary.html.twig', {
    summary: phil_reviews_summary('game', game.id),
    type: phil_reviews_type('game'),
    superlatives: phil_reviews_summary('game', game.id).topSuperlatives(phil_reviews_type('game').superlatives, 3),
}) }}
```

## Twig functions

| Function | What it gives |
|---|---|
| `phil_reviews_kit(recipe)` | The name under which a recipe of the kit is installed. |
| `phil_reviews_name(member)` | The name `ReviewerDirectory` gives an author, or `null` for an anonymised review. |
| `phil_reviews_summary(type, id)` | The `RatingSummary` of a subject, or `null` for a type not declared. |
| `phil_reviews_type(type)` | The `SubjectType` of a declared type, or `null`. |
| `phil_reviews_aggregate_rating(type, id)` | The `AggregateRating` node, or `null` ([Ratings](ratings.md#structured-data)). |
