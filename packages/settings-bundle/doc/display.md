# Display preferences

## What the reader chooses

The bundle declares four choices in the `display` section, each `system` by default, which follows the device:

| Setting | Choices | Root attribute | System media query |
|---|---|---|---|
| `display.theme` | `system`, `light`, `dark` | `data-theme` | `prefers-color-scheme` |
| `display.motion` | `system`, `reduce` | `data-motion` | `prefers-reduced-motion` |
| `display.contrast` | `system`, `more` | `data-contrast` | `prefers-contrast` |
| `display.text_size` | `system`, `large`, `larger` | `data-text-size` | the browser's text size |

`display: false` leaves them out. Any choice an application declares with an `html_attribute` works the same way.

## On the page

`phil_document_attributes()` writes the attributes of the current user's or visitor's choices on the root element,
except a choice at its default, which writes nothing:

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
{# <html lang="fr" data-motion="reduce" data-text-size="large"> #}
```

Without an attribute, the stylesheet keeps to the system's media queries. `phil/ux-css` reads the four attributes as
it reads the queries: see its documentation of the elements. Names and values are restricted to lowercase letters,
digits, `_` and `-` by their declaration, so they are written without escaping.

## For visitors

A setting kept for visitors, as the display preferences are, is saved for a visitor in the cookie `phil_preferences`:
`display.motion=reduce&display.theme=dark`, the values that differ from the default, sorted. The cookie holds only what
the visitor asked the site to remember about its own display, so it is strictly necessary and needs no consent banner.
It lasts a year, is sent over HTTPS when the request is, cannot be read by scripts, and stays out of other sites'
requests (`SameSite=Lax`). Every default clears it.

The cookie is read leniently and bounded: past 1 024 bytes it is ignored, past its 32nd pair the rest is, and an
unknown key, a setting not kept for visitors or a refused value is left out. A signed-in user's display preferences are
saved with their settings and written to the cookie as well, so that the site keeps its look once they sign out.

## As soon as it is chosen

The form of a section holding such a choice carries the Stimulus controller `phil--settings-bundle--document`, which
writes the attribute on the root element as soon as a radio button is chosen, before the form is saved. With
AssetMapper, the bundle serves it without a build step; it registers through `assets/controllers.json`, as any Symfony
UX package.

Without JavaScript, the choice applies once saved.
