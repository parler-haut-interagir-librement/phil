# Presenter view

The presenter view shows the speaker each slide with its notes, the next slide and a timer. It holds the notes, so it
is served to the speaker only: through the presenter routes of the bundle, which ask for a role, or at an address of
the application's own, behind its own access control. The audience page is always a route of the application's own.

## The presenter routes

The bundle ships an optional routes file, `config/routes.php`, with two routes:

| Route | Path | What it serves |
|---|---|---|
| `phil_slides_presenter` | `/{deck}` | The presenter view of a deck, in the layout `phil_slides.layout` |
| `phil_slides_presenter_notes_pdf` | `/{deck}/notes.pdf` | The PDF of the deck with its notes, as a download; needs `phil/paged-media-bundle` |

Nothing is served until the application imports the file, under the prefix of its choice and behind its firewall:

```yaml
# config/routes/phil_slides.yaml
phil_slides_presenter:
    resource: '@PhilSlidesBundle/config/routes.php'
    prefix: /presenter
```

The decks come from a service of the application that implements `Phil\Slides\Application\Contract\PresentedDecks`:
it finds a deck by the identifier in the address and returns a `Presentation` — the deck, the address of its audience
page and the length of the talk in minutes —, or `null`.

```php
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\Presentation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TalkDecks implements PresentedDecks
{
    public function __construct(private TalkRepository $talks, private DeckReader $reader, private UrlGeneratorInterface $urls)
    {
    }

    public function find(string $id): ?Presentation
    {
        $talk = $this->talks->findOneBySlug($id);

        return null === $talk ? null : new Presentation(
            $this->reader->fromMarkup($talk->source)->withId($id),
            $this->urls->generate('talk_show', ['slug' => $id]),
            $talk->minutes,
        );
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\Slides\Application\Contract\PresentedDecks: '@App\Slides\TalkDecks'
```

Until the application names its own, the bundle's `NoPresentedDecks` finds none and both routes answer 404. An
identifier is 1 to 128 letters, digits, `_`, `-`, `.` or `:`, as `Deck` accepts it; any other address is not matched.
Use the same identifier for the audience page, so that both windows open the same channel.

## Security

Both routes ask the authorization checker of Symfony Security for the role `phil_slides.presenter_role`,
`ROLE_SLIDES_PRESENTER` by default, before anything else:

| Who asks | Answer |
|---|---|
| A user who holds the role | The page, or the PDF |
| A user signed in without the role | 403, whether the deck exists or not |
| Someone not signed in | What the firewall's entry point answers: a sign-in page, or 401 |
| Anyone, without `symfony/security-bundle` | 403: without an authorization checker, the routes are refused to everyone |
| A user who holds the role, for a deck nobody has | 404 |

The role is checked before the deck is looked for, so a visitor learns nothing of which decks exist. Both responses
are sent with `Cache-Control: no-store, private` and `X-Robots-Tag: noindex, nofollow`; the PDF also with
`X-Content-Type-Options: nosniff` and a `Content-Disposition` of `attachment`, its name the deck's identifier followed
by `-notes.pdf`. Give the role to the speakers through the role hierarchy, or name another one:

```yaml
phil_slides:
    presenter_role: ROLE_SPEAKER
```

The page extends `phil_slides.layout`, `@PhilSlides/layout.html.twig` by default, a bare page with the blocks `title`
and `main`. Name the application's own layout, which loads the controllers and the stylesheet of the bundle, with the
same two blocks.

## The template

An application that serves the presenter view itself includes the template, behind its own access control:

```twig
{{ include('@PhilSlides/presenter.html.twig', {deck: slides_markup(talk.source, 'markdown', 'talk-' ~ talk.id), audience_url: path('talk_show', {id: talk.id}), duration: 20}) }}
```

| Variable | Default | What it does |
|---|---|---|
| `deck` | required | The `Deck`, the same as the audience's |
| `audience_url` | none | A link to the audience page, opened in a window of its own |
| `notes_pdf_url` | none | A link that downloads the PDF of the deck with its notes |
| `duration` | none | The length of the talk in minutes: the timer shows the time left too |
| `id` | `phil-slides-presenter` | The `id` of the element, and the prefix of its headings' |

The buttons and the links are the recipe `Button` of the kit the application installed (see
[Installation](installation.md)).

## Without script

Each slide is followed by its notes, or by *No notes for this slide*: a script to read from, or to print.

## With the controller

`phil--slides--presenter` marks the view `isPresenting` and shows the current slide with its notes; the fragments still
to come stay visible to the speaker, faded. Beside them:

- the next slide, a copy without identifiers, every fragment shown, hidden from assistive technologies; *End of the
  deck* after the last one;
- the timer: the time elapsed, and with `duration` the time left, negative and marked `isOvertime` once past; *Start*
  and *Pause* on one toggle button, which says whether it is pressed, *Reset*;
- *Previous*, *Next* and the counter.

The keys of the audience page move the deck wherever the focus is; the live region says each new slide.

## Two windows, no server

Each window of a deck opens a `BroadcastChannel` named `phil-slides:` followed by the deck's identifier. A window that
opens says hello; the others answer with their position — the slide and the number of its fragments shown —, so a
window opened late joins at the current slide. A move in any window is sent to all, and a window that receives one
follows it without sending it again. Any number of audience windows follow the presenter's.

A `BroadcastChannel` joins the windows of one browser on one origin: open the presenter view and the audience page in
the same browser, from the same site. Both must render the same deck under the same identifier; give it a stable one —
`slides_markup(source, 'markdown', 'talk-42')` or `Deck::withId()` — when the deck may be edited between the two.

The link of each page opens the other in a window of its own, a popup the presenter moves to the second screen;
without script, or when the browser blocks it, the link opens a tab.
