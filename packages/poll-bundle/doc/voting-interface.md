# The voting interface

The bundle gives the bundles built on the polls — a poll of availabilities, a questionnaire, a box of ideas, an
assembly — and the application one way to show a poll: the form of its ballot and its results, as views a template
draws. The templates draw their parts with the kit's recipes, installed from the PHIL UX kit or, on Tailwind, from the
Shadcn kit. The page that shows them keeps its own route, its own controller and its own words around them.

## The views

`PollViews` builds them for one viewer, a reference of `phil/resource`, or `null` for someone not signed in.

`ballot($poll, $viewer)` gives a `BallotView`:

| Field | What it holds |
|---|---|
| `poll`, `question` | The identifier and the question. |
| `kind` | `single`, `multiple`, `ranked` or `scale`. |
| `choices` | One `BallotChoice` per option, in the declared order — the marks of a scale from the lowest —: `id`, `label`, `chosen`, and `rank` on a ranked ballot. |
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

The results of a scale list one line per mark, from the lowest, the score being the ballots that gave it, and no mark
wins: their distribution is what is shown. `rating` holds the `Rating` of `phil/poll` — the ballots that gave a mark,
the sum of the marks, and `mean()` —, and `netPromoter`, for a poll counted by `nps`, its `NetPromoter` — promoters,
passives, detractors and `score()`. Both are `null` for the other kinds.

## In a template

With Twig, the functions `phil_poll_ballot(poll, viewer)` and `phil_poll_results(poll, viewer)` give the views; the
viewer is a reference as text (`'user:7'`), as an object, or left out for someone not signed in. The templates draw
them:

```twig
{{ include('@PhilPoll/ballot.html.twig', {
    ballot: phil_poll_ballot(poll.id, member),
    action: path('idea_vote', {id: idea.id}),
    csrf_token: csrf_token('idea_vote'),
    errors: errors ?? [],
}) }}

{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll.id, member)}) }}
```

`@PhilPoll/ballot.html.twig` takes the view, the address the form posts to, when the page checks one, a CSRF token
posted as `_token`, and, when a ballot was refused, `errors`: its messages, already translated. It draws a `fieldset`
named by the question, a sentence of help, the choices, the errors and a button. A single ballot is a group of radio
buttons, with a blank ballot when the poll accepts one, checked when it is the viewer's; a scale, the same group laid
out in a row, one radio button per mark; a multiple ballot, checkboxes; a ranked ballot, a select of ranks per option.
The fieldset is disabled, without a button, when the viewer may neither cast nor change a ballot; a sentence says
why. Embedding it replaces a block: `choices`, `status` or `actions`. The
`choices` block is drawn inside the fieldset, after its legend and its help; a block draws the recipes through the map
`kit`:

```twig
{% embed '@PhilPoll/ballot.html.twig' with {ballot: phil_poll_ballot(poll.id, member), action: path('poll_vote', {id: poll.id})} %}
    {% block choices %}
        {% for choice in ballot.choices %}
            {% component (kit.Field) with {orientation: 'horizontal'} %}
                {% block content %}
                    {{ component(kit.Checkbox, {name: 'choices[]', value: choice.id, id: 'choice-' ~ choice.id, checked: choice.chosen}) }}
                    {% component (kit.FieldLabel) with {for: 'choice-' ~ choice.id} %}{% block content %}{{ choice.label }}{% endblock %}{% endcomponent %}
                {% endblock %}
            {% endcomponent %}
        {% endfor %}
    {% endblock %}
{% endembed %}
```

`@PhilPoll/results.html.twig` takes the view. It draws the question, the ballots counted and one line per option with
its bar, its score and, for the winners, the word that says so; or a sentence while the results are hidden. A scale
adds its mean mark, a Net Promoter Score its score and its three groups, written with the separators of the domain's
`number.decimal` and `number.thousands`. Embedding it replaces `lines` or `rating`, or fills `revealed`.

The parts are the recipes `Field`, `RadioGroup`, `Checkbox`, `NativeSelect`, `Button` and `Progress`, named by
`phil_poll_kit()` after the subdirectory where the application installed them, as
[Installation](installation.md#the-recipes-of-the-kit) shows, with `Field:Error` for the errors. The templates give the
recipes only what both kits read the same way: each radio button its name, its checked state and whether it is required,
as the Shadcn group passes none of them to its buttons; each bar its share of the highest score, from 0 to 100, with the
score as its `aria-valuetext`, as the Shadcn `Progress` takes a percentage. The words come from the translation domain
`phil_poll`, in English and in French; an application overrides them in its own `translations/phil_poll.<locale>.php`,
and a template under `templates/bundles/PhilPollBundle/`.

## Reading what the form posts

`BallotForm::choices($rules, $fields)` reads the fields the form posts into the choices of a ballot: `choice` for a
single ballot or the mark of a scale, `choices[]` for a multiple one, `ranks[option]` for a ranked one, an empty rank
leaving the option out.
Anything else than what the form sends — a list where a text is expected, an option the poll does not have, a rank
that is not a number from 1 to 999, two options sharing a rank — throws `InvalidBallot`. `Polls` checks the choices
again against the rules of the poll.

```php
use Phil\Poll\Exception\InvalidBallot;
use Phil\PollBundle\Application\BallotForm;

#[Route('/ideas/{id}/vote', methods: ['POST'])]
public function vote(string $id, Request $request): Response
{
    $poll = $this->polls->get($this->ideas->pollOf($id));

    try {
        $choices = BallotForm::choices($poll->rules(), $request->request->all());
        $this->polls->hasVoted($poll->id, $this->member())
            ? $this->polls->change($poll->id, $this->member(), $choices)
            : $this->polls->cast($poll->id, $this->member(), $choices);
    } catch (InvalidBallot $refused) {
        return $this->render('idea/show.html.twig', ['idea' => $id, 'errors' => [$refused->getMessage()]], new Response(status: 422));
    }

    return $this->redirectToRoute('idea_show', ['id' => $id], Response::HTTP_SEE_OTHER);
}
```

The page that receives the form checks its CSRF token, as any form of the application. The message of `InvalidBallot`
is in English; an application that speaks another language passes its own words in `errors`.

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
| A single ballot, a scale | Native radio buttons sharing a name, in a `radiogroup` named by the question: the arrows move between them and check one |
| A ranked ballot | A native select per option, reachable by the keyboard, read with its label |
| A ballot refused | Its messages in an `alert` region, which the fieldset names with `aria-describedby`, the fieldset marked `aria-invalid` |
| Why the form cannot be sent | A sentence in a `status` region; the controls are disabled, not hidden |
| A result | Each bar named by its option with `aria-labelledby`, its score as `aria-valuetext`; the score written beside it |

Everything works without JavaScript: the form is a plain form, and the results a list. The browser suite checks it in
Chrome with both kits: each kind of ballot filled and sent with the keyboard alone, the names and roles Chrome gives
the groups, the controls and the bars, the alert of a ballot refused, and the form disabled for someone signed out
and once the poll is closed.

## Later

A word cloud and a brainstorm, whose answers are free text and so call for moderation, and the counting by grades and
by majority judgment are built when a site asks for them; none is part of the bundle yet.
