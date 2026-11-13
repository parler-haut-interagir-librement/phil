# The manifest

`/manifest.webmanifest` answers the manifest as `application/manifest+json`, with `Cache-Control: public,
max-age=<cache_max_age>` and `X-Content-Type-Options: nosniff`. The members follow the Web Application Manifest
specification; those not given are left out.

| Member | Rule |
|---|---|
| `name` | One line of 1 to 120 characters, without control characters. |
| `short_name` | One line of 1 to 30 characters: the name under the icon. |
| `description` | One line of 1 to 300 characters. |
| `id` | A path of the site: the identity of the installed site, kept when `start_url` changes. |
| `start_url` | A path of the site within `scope`, a query allowed — `/?source=pwa`. |
| `scope` | A path of the site: the pages that belong to the installed site; others open in the browser. |
| `display` | `fullscreen`, `standalone`, `minimal-ui` or `browser`, the modes every browser falls back along. |
| `display_override` | Modes asked for first, each once: the four above, `window-controls-overlay`, `tabbed`. |
| `orientation` | An orientation of the Screen Orientation API. |
| `theme_color`, `background_color` | `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa` or a CSS colour keyword. |
| `lang`, `dir` | A language tag such as `fr-CA`; `ltr`, `rtl` or `auto`. |
| `categories` | 10 at most, each once, lower-case words such as `books` or `events`. |
| `icons` | 30 at most. |
| `shortcuts` | 10 at most, each within `scope`. |

## Full screen

`display: fullscreen` opens the installed site on the whole screen, without the browser's interface — for a screen in
a hall, a kiosk or a game table; a browser that cannot falls back on `standalone`, then `minimal-ui`. A page outside an
installed site enters the full screen on a gesture with the `fullscreen` action of the controller
([JavaScript](javascript.md)).

## Icons

| Key | Rule |
|---|---|
| `src` | A path of the site from its root, or the logical path of an asset — `images/icon-512.png` —, given at the address the Asset component gives it: with AssetMapper, its versioned file. |
| `sizes` | `any`, or up to 10 sizes such as `192x192 512x512`. |
| `type` | The media type of an image, such as `image/png`. |
| `purpose` | `any`, `maskable`, `monochrome`, each once; `any` when empty. |

A site gives at least a 192 and a 512 pixel icon, and a `maskable` one the operating system can crop.

## Shortcuts

A shortcut has a `name` of 60 characters at most, a `url` within the scope, and an optional `short_name` and
`description`; the operating system offers it on the icon of the installed site.
