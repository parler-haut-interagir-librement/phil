# JavaScript

Two Stimulus controllers, in `assets/src`, served by AssetMapper under `@phil/pwa-bundle`, with `@hotwired/stimulus`
as a peer dependency. Neither needs a build step.

## phil--pwa-bundle--pwa

```twig
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
    <button hidden data-phil--pwa-bundle--pwa-target="install" data-action="phil--pwa-bundle--pwa#install">Install</button>
    <button hidden data-phil--pwa-bundle--pwa-target="update" data-action="phil--pwa-bundle--pwa#update">Update</button>
    <button data-action="phil--pwa-bundle--pwa#fullscreen">Full screen</button>
    <p hidden data-phil--pwa-bundle--pwa-target="offline">You are offline: the pages already opened still work.</p>
</body>
```

| Value | Default | Role |
|---|---|---|
| `url` | — | The address of the worker; without it, nothing is registered. |
| `scope` | `/` | The scope it is registered with. |

| Target | Shown |
|---|---|
| `install` | While the browser offers to install the site (`beforeinstallprompt`). |
| `update` | While a new worker waits and a page is controlled by the old one. |
| `offline` | While the browser is offline; the element also carries `data-offline`. |

| Action | Does |
|---|---|
| `install` | Shows the browser's install prompt, once. |
| `update` | Asks the waiting worker to take over, then reloads the page when it has. |
| `fullscreen` | Enters the full screen, or leaves it. |

Events, prefixed by `phil--pwa-bundle--pwa:`: `registered` (with the `scope`), `unsupported`, `failed` (with the
`error`), `installable`, `install-choice` (with the `outcome`), `installed`, `update-available`, `online`, `offline`.

## phil--pwa-bundle--presentation

```twig
{# the page that presents #}
<div {{ stimulus_controller('phil--pwa-bundle--presentation', {url: path('app_screen')}) }}>
    <button data-action="phil--pwa-bundle--presentation#start">Present</button>
    <button data-action="phil--pwa-bundle--presentation#send" data-phil--pwa-bundle--presentation-message-param='{"slide": 2}'>Slide 2</button>
    <button data-action="phil--pwa-bundle--presentation#stop">Stop</button>
</div>

{# the page shown on the second screen #}
<main {{ stimulus_controller('phil--pwa-bundle--presentation', {receiver: true}) }}
      data-action="phil--pwa-bundle--presentation:message->screen#show">
```

| Value | Default | Role |
|---|---|---|
| `url` | — | The page to show on the second screen. |
| `channel` | `phil-pwa-presentation` | The name of the window and of the BroadcastChannel of the fallback. |
| `receiver` | `false` | The page shown: it listens instead of presenting. |

`start` asks the browser for a display with the **Presentation API** — a second screen, a cast device — and opens the
page there; messages go through the presentation connection as JSON. Where the API is missing, or finds no display,
`start` opens the page in a **window of its own** — once, while it stays open —, which the person moves to the second
screen and puts in full screen, and messages go through a **BroadcastChannel** of the site's origin. When the person
cancels the choice of a display, nothing opens. `send` takes the `message` parameter of the action, or the `detail` of
the event; `stop` ends the presentation or closes the window.

Events, prefixed by `phil--pwa-bundle--presentation:`: `connected` (with the `mode`, `presentation` or `window`),
`cancelled`, `message` (with its `data`), `disconnected`.

Both controllers keep their state on their element only, and remove their listeners when it leaves the page.
