<!-- phil-dev source-sha256=87e48d77f3cc2ae08693886ef44d82eab2d7f33d7416a65a035e858ed522148f -->
# JavaScript

Deux contrôleurs Stimulus, dans `assets/src`, servis par AssetMapper sous `@phil/pwa-bundle`, avec `@hotwired/stimulus`
en dépendance homologue. Aucun n'a besoin d'une étape de construction.

## phil--pwa-bundle--pwa

```twig
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
    <button hidden data-phil--pwa-bundle--pwa-target="install" data-action="phil--pwa-bundle--pwa#install">Install</button>
    <button hidden data-phil--pwa-bundle--pwa-target="update" data-action="phil--pwa-bundle--pwa#update">Update</button>
    <button data-action="phil--pwa-bundle--pwa#fullscreen">Full screen</button>
    <p hidden data-phil--pwa-bundle--pwa-target="offline">You are offline: the pages already opened still work.</p>
</body>
```

| Valeur | Par défaut | Rôle |
|---|---|---|
| `url` | — | L'adresse du worker ; sans elle, rien n'est enregistré. |
| `scope` | `/` | La portée avec laquelle il est enregistré. |

| Cible | Montrée |
|---|---|
| `install` | Tant que le navigateur propose d'installer le site (`beforeinstallprompt`). |
| `update` | Tant qu'un nouveau worker attend et qu'une page est contrôlée par l'ancien. |
| `offline` | Tant que le navigateur est hors ligne ; l'élément porte aussi `data-offline`. |

| Action | Fait |
|---|---|
| `install` | Montre l'invite d'installation du navigateur, une seule fois. |
| `update` | Demande au worker en attente de prendre le relais, puis recharge la page quand il l'a fait. |
| `fullscreen` | Entre dans le plein écran, ou en sort. |

Les événements, préfixés par `phil--pwa-bundle--pwa:` : `registered` (avec le `scope`), `unsupported`, `failed` (avec
l'`error`), `installable`, `install-choice` (avec l'`outcome`), `installed`, `update-available`, `online`, `offline`.

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

| Valeur | Par défaut | Rôle |
|---|---|---|
| `url` | — | La page à montrer sur le second écran. |
| `channel` | `phil-pwa-presentation` | Le nom de la fenêtre et du BroadcastChannel du repli. |
| `receiver` | `false` | La page montrée : elle écoute au lieu de présenter. |

`start` demande au navigateur un écran avec l'**API Presentation** — un second écran, un appareil de diffusion — et y
ouvre la page ; les messages passent par la connexion de présentation en JSON. Là où l'API manque, ou ne trouve aucun
écran, `start` ouvre la page dans une **fenêtre à part** — une seule fois, tant qu'elle reste ouverte —, que la
personne déplace sur le second écran et met en plein écran, et les messages passent par un **BroadcastChannel** de
l'origine du site. Quand la personne annule le choix d'un écran, rien ne s'ouvre. `send` prend le paramètre `message`
de l'action, ou le `detail` de l'événement ; `stop` termine la présentation ou ferme la fenêtre.

Les événements, préfixés par `phil--pwa-bundle--presentation:` : `connected` (avec le `mode`, `presentation` ou
`window`), `cancelled`, `message` (avec ses `data`), `disconnected`.

Les deux contrôleurs gardent leur état sur leur seul élément, et retirent leurs écouteurs quand il quitte la page.
