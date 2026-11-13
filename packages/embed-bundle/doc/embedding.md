# Embedding

## Declaring an embed

An embed is a name in the configuration, with the origins allowed to frame it, its themes and how long its tokens hold.
Its page is any route of the application, marked by its `_embed` default or by the `#[Embeddable]` attribute of its
controller:

```php
use Phil\EmbedBundle\Application\Attribute\Embeddable;

#[Route('/embed/calendar/{id}', name: 'calendar_embed')]
#[Embeddable('calendar')]
final readonly class CalendarEmbedController
{
    public function __invoke(string $id): Response { /* … */ }
}
```

## The page

A request to that page that carries `embed_token` is read once the controller is known:

- a valid token of that embed, for an origin it allows: the request is given its context, the page may be framed by that
  origin alone, and `X-Frame-Options` is dropped;
- any other token: the page may be framed by none — the frame stays empty, and the element keeps its link;
- no token: the page is answered as any other, under the site's own framing rules.

The context is the request attribute `_phil_embed`, an `EmbedContext`, and `phil_embed()` in Twig: the embed, the
origin, the theme, the subject, and the themes the partner may switch to. The theme is the one the frame's address
asks for in `embed_theme`, if the embed has it, else the token's, else the embed's default.

```twig
<html lang="en" {{ phil_embed_frame_attributes() }}>
<body>
    {% set embed = phil_embed() %}
    {% if embed %}<p>Shown on {{ embed.origin }}</p>{% endif %}
</body>
</html>
```

`phil_embed_frame_attributes()` writes, inside an embed only, the attributes that start the frame controller with the
token's origin and the embed's themes, and `data-theme`.

A page shown in a frame of another site is a third-party context: browsers send it no cookie of the site unless the
cookie is `SameSite=None; Secure`, and partition it then. An embed meant for visitors who are signed in needs its own
decision about cookies; the bundle does not change them.

## The code a partner pastes

`EmbedCodes::snippet()`, `phil_embed_code()` in Twig, writes it:

```twig
<pre>{{ phil_embed_code('calendar', 'https://partner.example', 'calendar_embed', {id: event.id}, url('calendar_show', {id: event.id}), 'Our calendar', 'dark', 'event:' ~ event.id) }}</pre>
```

```html
<phil-embed src="https://site.example/embed/calendar/7?embed_token=pe1.hs256.…" title="Our calendar" theme="dark">
    <a href="https://site.example/calendar/7">Our calendar</a>
</phil-embed>
<script type="module" src="https://site.example/_embed/phil-embed.js" crossorigin="anonymous"></script>
```

The arguments are the embed, the partner's origin, the page's route and its parameters, the absolute address a reader
without the frame goes to, the title — the frame's and the link's —, and optionally the theme and the subject. The
token is issued for that origin: an origin the embed does not allow, a theme it lacks, or a fallback that is no
address is refused. `phil:embed:token <embed> <origin> [--theme] [--subject]` issues one from the console.

## The element

`<phil-embed>` is the one piece of the bundle that runs on other sites, where Stimulus is not: a custom element, in the
module the bundle's route serves.

| Attribute | What it does |
|---|---|
| `src` | The frame's address, `http` or `https`; the element does nothing with another. |
| `title` | The frame's title; the link's text when absent. |
| `theme` | The theme asked of the page, passed in `embed_theme`, and sent to the frame when it changes. |
| `height` | The frame's height before the page tells its own, 150 pixels by default. |
| `timeout` | How long the element waits for the page to say it is ready, 10,000 milliseconds by default. |
| `sandbox` | The frame's sandbox; by default scripts, forms, popups and the page's own origin are allowed. |

It creates a hidden frame, lazily loaded, without referrer. When the page says it is ready, it shows the frame, hides
the link and dispatches `phil-embed:ready`; each height the page tells it sets the frame's and dispatches
`phil-embed:resize` with `{height}`. If the page never says it is ready — refused framing, a network error —, the
element removes the frame and keeps the link. `data-state` says where it is: `loading`, `ready` or `fallback`.

## Comments carried by another site's forum

`RemoteCommentThreads` is the port of a page's comments carried by the forum of another site. The bundle declares it
and ships no adapter: it is written with the forum.
