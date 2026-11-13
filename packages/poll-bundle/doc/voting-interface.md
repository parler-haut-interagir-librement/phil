# The voting interface

The bundle gives the bundles built on the polls — a poll of availabilities, a questionnaire, a box of ideas, an
assembly — and the application one way to show a poll: the form of its ballot and its results, as views a template
draws. The templates write the markup of the kit's recipes, so that `phil/ux-css` draws them as it draws the recipes,
whether the recipes are installed or not. The page that shows them keeps its own route, its own controller and its
own words around them.

## The views

`PollViews` builds them for one viewer, a reference of `phil/resource`, or `null` for someone not signed in.

`ballot($poll, $viewer)` gives a `BallotView`:

| Field | What it holds |
|---|---|
| `poll`, `question` | The identifier and the question. |
| `kind` | `single`, `multiple` or `ranked`. |
| `choices` | One `BallotChoice` per option, in the declared order: `id`, `label`, `chosen`, and `rank` on a ranked ballot. |
| `minChoices`, `maxChoices`, `abstention` | The bounds of a ballot, and whether a blank one is accepted. |
| `anonymous`, `seats` | Whether ballots are kept apart from their voters; the seats the count fills. |
| `open`, `voted` | Whether the poll is open now; whether the viewer cast a ballot. |
| `mayCast`, `mayChange` | Whether the viewer may cast a ballot now, or change theirs. `isEditable()` is either. |

The viewer's ballot fills `chosen` and `rank` when the poll is not anonymous: an anonymous ballot is known only to whoever
holds its receipt. `mayCast` asks the policy; `mayChange` is never true in an anonymous poll, whose ballots are changed
by receipt.

`results($poll, $viewer)` gives a `ResultsView`, or `null` while the results are not the viewer's to see, as
[Results](results.md) says. It holds the method, the ballots and abstentions counted, the seats, `revealed` — the poll
is closed — and one `ResultLine` per option, in the order of the standings: `label`, `place`, `score`, `display`,
`max` and `winner`. `max` is the highest score, at least 1, for the length of a bar. `display` is the score as it is
written: a single transferable vote counts in billionths of a vote, written with two decimals, truncated (`2.71`).
`winners()` lists the labels of the options that won or were elected.

## In a template

With Twig, the functions `phil_poll_ballot(poll, viewer)` and `phil_poll_results(poll, viewer)` give the views; the
viewer is a reference as text (`'user:7'`), as an object, or left out for someone not signed in. The templates draw
them:

```twig
{{ include('@PhilPoll/ballot.html.twig', {
    ballot: phil_poll_ballot(poll.id, member),
    action: path('idea_vote', {id: idea.id}),
    csrf_token: csrf_token('idea_vote'),
}) }}

{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll.id, member)}) }}
```

`@PhilPoll/ballot.html.twig` takes the view, the address the form posts to and, when the page checks one, a CSRF token
posted as `_token`. It draws a `fieldset` named by the question, a sentence of help, the choices and a button. A single
ballot is a group of radio buttons, with a blank ballot when the poll accepts one; a multiple ballot, checkboxes; a
ranked ballot, a select of ranks per option. The fieldset is disabled, without a button, when the viewer may neither
cast nor change a ballot; a sentence says why. Embedding it replaces a block: `choices`, `status` or `actions`.

`@PhilPoll/results.html.twig` takes the view. It draws the question, the ballots counted and one line per option with
its bar, its score and, for the winners, the word that says so; or a sentence while the results are hidden. Embedding
it replaces `lines`, or fills `revealed`.

The markup is the one of the recipes `Field`, `RadioGroup`, `Checkbox`, `NativeSelect`, `Button` and `Progress`: their
`data-slot` attributes and `isX` classes. The words come from the translation domain `phil_poll`, in English and in
French; an application overrides them in its own `translations/phil_poll.<locale>.php`, and a template under
`templates/bundles/PhilPollBundle/`.

## Reading what the form posts

`BallotForm::choices($rules, $fields)` reads the fields the form posts into the choices of a ballot: `choice` for a
single ballot, `choices[]` for a multiple one, `ranks[option]` for a ranked one, an empty rank leaving the option out.
Anything else than what the form sends — a list where a text is expected, an option the poll does not have, a rank
that is not a number from 1 to 999, two options sharing a rank — throws `InvalidBallot`. `Polls` checks the choices
again against the rules of the poll.

```php
use Phil\PollBundle\Application\BallotForm;

#[Route('/ideas/{id}/vote', methods: ['POST'])]
public function vote(string $id, Request $request): Response
{
    $poll = $this->polls->get($this->ideas->pollOf($id));
    $choices = BallotForm::choices($poll->rules(), $request->request->all());

    $this->polls->hasVoted($poll->id, $this->member())
        ? $this->polls->change($poll->id, $this->member(), $choices)
        : $this->polls->cast($poll->id, $this->member(), $choices);

    return $this->redirectToRoute('idea_show', ['id' => $id], Response::HTTP_SEE_OTHER);
}
```

The page that receives the form checks its CSRF token, as any form of the application.

## Celebrating revealed results

Once a poll is closed, its results are revealed. Two hooks let a page celebrate them, through the kit's `Celebration`
for one, without the bundle knowing it.

**The DOM event.** The results carry the Stimulus controller `phil--poll-bundle--results`, from the bundle's
`assets/package.json`. When the results it draws are revealed, it dispatches `poll:revealed` on the window, its detail
naming the poll and the labels of its winners, once per poll in a browser tab. The kit's recipe plays on it:

```twig
<twig:Celebration message="{{ 'idea.chosen'|trans }}" data-action="poll:revealed@window->celebration#play" />
```

**The Twig block.** The block `revealed` of `@PhilPoll/results.html.twig` is drawn only for revealed results:

```twig
{% embed '@PhilPoll/results.html.twig' with {results: phil_poll_results(poll.id, member)} %}
    {% block revealed %}<twig:Celebration message="{{ 'poll.revealed'|trans }}" :autoplay="true" />{% endblock %}
{% endembed %}
```

The recipe says its message in a polite live region and draws nothing where motion is reduced.

## Accessibility

| What | How |
|---|---|
| The question | The legend of the fieldset, which names its choices |
| The bounds of a ballot | A sentence of help, which the fieldset names with `aria-describedby` |
| Each choice | A `label` tied to its control by `for` |
| A ranked ballot | A native select per option, reachable by the keyboard, read with its label |
| Why the form cannot be sent | A sentence in a `status` region; the controls are disabled, not hidden |
| A result | Each bar named by its option with `aria-labelledby`; the score written beside it |

Everything works without JavaScript: the form is a plain form, and the results a list.

## Later

The forms of a question that is not scored — a scale, a word cloud, a recommendation index, a brainstorm — and the
counting by grades and by majority judgment are built when a site asks for them; none is part of the bundle yet.
