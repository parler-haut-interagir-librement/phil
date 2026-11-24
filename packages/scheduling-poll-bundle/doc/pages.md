# Pages and templates

## The routes

`config/routes.php` declares the two pages of a respondent without an account, served by `AnswerController`:

| Route | Path | What it does |
|---|---|---|
| `phil_scheduling_poll_join` | `/{poll}/join`, GET and POST | The poll's page: its dates, and a form that asks a name and, optionally, an address; posted, it takes the respondent to their own link. |
| `phil_scheduling_poll_answer` | `/answer?token=…`, GET and POST | The respondent's page: the table of the dates and their answers; posted, it saves them and comes back with `saved=1`. |

Both answer with `Referrer-Policy: no-referrer`, so that a link out of the page never carries the token,
`Cache-Control: private, no-store` and `X-Robots-Tag: noindex`. A link that opens nothing answers 404 with one sentence
for every reason; answers refused answer 422 with the message. When the application has `symfony/security-csrf`, both
forms carry a CSRF token. A page for the organizer — creating a poll, inviting, closing — belongs to the application,
which calls `SchedulingPolls` and draws `@PhilSchedulingPoll/results.html.twig`.

## The templates

| Template | Variables | Blocks |
|---|---|---|
| `@PhilSchedulingPoll/answer.html.twig` | `sheet`, `token`, `action`, `csrf_token`, `errors`, `saved` | `chosen`, `celebration`, `answers`, `results` |
| `@PhilSchedulingPoll/join.html.twig` | `poll`, `slots`, `open`, `action`, `csrf_token`, `errors`, `values` | `fields` |
| `@PhilSchedulingPoll/results.html.twig` | `results`, `celebrate` | `chosen`, `celebration`, `table` |

The answer page is a form around a table: one row per date, its header the date in a `time` element, then three cells,
each a radio button — yes, if need be, no — named `answers[<slot>]`. The buttons of a row are one group: Tab reaches
it, the arrows move within it, Space chooses. Each button's accessible name is its row's date and its column's word,
through `aria-labelledby`. Without JavaScript, the form posts and the page comes back. The table sits in the kit's
scrolling container, and the table of all the answers in a region that the keyboard reaches (`tabindex="0"`), so that a
screen of 375 pixels shows every date. Each answer in that table is written as a sign for the eye and in words for a
screen reader.

The dates are written by `format_datetime` of `twig/intl-extra`, in the language of the page; the words come from the
translation domain `phil_scheduling_poll`, in English and in French. An application overrides them in its own
`translations/phil_scheduling_poll.<locale>.php`, and a template under `templates/bundles/PhilSchedulingPollBundle/`.

With Twig, three functions:

| Function | Gives |
|---|---|
| `phil_scheduling_poll_results(poll, organizer, timezone)` | The `ResultsView` of the organizer; the organizer as a reference, text or object. |
| `phil_scheduling_poll_timezones()` | The time zones of the IANA database, for the select of the answer page. |
| `phil_scheduling_poll_kit(recipe)` | The name of a recipe of the kit as the application installed it, `ui:Table:Row`. |

```twig
{{ include('@PhilSchedulingPoll/results.html.twig', {results: phil_scheduling_poll_results(poll.id, app.user.reference)}) }}
```

## The Stimulus controllers

`assets/package.json` declares two controllers for Symfony UX:

| Controller | What it adds |
|---|---|
| `phil--scheduling-poll-bundle--timezone` | On a first visit, picks the browser's time zone in the select, when it offers it; writes every date again, with the browser's own words, in the zone picked, before anything is posted. |
| `phil--scheduling-poll-bundle--chosen` | Once the date is chosen, dispatches the DOM event `scheduling-poll:chosen` on the window, its detail the poll and the date's start, once per poll in a browser tab. |

Without them the page works the same: the dates are written in the zone the page was given, and the zone posted is used
from then on.

## The celebration

The date chosen is a victory: the `celebration` block, empty, is drawn beside it, for the kit's Celebration, which plays
on the event of the `chosen` controller. It says its message in a polite live region, and draws its confetti only where
motion is not reduced:

```twig
{% embed '@PhilSchedulingPoll/answer.html.twig' %}
    {% block celebration %}
        <twig:Celebration message="{{ 'poll.date_chosen'|trans }}" data-action="scheduling-poll:chosen@window->celebration#play" />
    {% endblock %}
{% endembed %}
```

On the server side, the event `DateChosen` is dispatched once, by the closing that won.
