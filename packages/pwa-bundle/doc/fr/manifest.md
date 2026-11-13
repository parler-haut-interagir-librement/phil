<!-- phil-dev source-sha256=06798b964e228d3d3d327e26c65d3a6668467b349a6eb3033a2ebfc9ba941cfb -->
# Le manifeste

`/manifest.webmanifest` répond avec le manifeste en `application/manifest+json`, avec `Cache-Control: public,
max-age=<cache_max_age>` et `X-Content-Type-Options: nosniff`. Les membres suivent la spécification Web Application
Manifest ; ceux qui ne sont pas donnés sont omis.

| Membre | Règle |
|---|---|
| `name` | Une ligne de 1 à 120 caractères, sans caractères de contrôle. |
| `short_name` | Une ligne de 1 à 30 caractères : le nom sous l'icône. |
| `description` | Une ligne de 1 à 300 caractères. |
| `id` | Un chemin du site : l'identité du site installé, conservée quand `start_url` change. |
| `start_url` | Un chemin du site dans `scope`, avec une requête permise — `/?source=pwa`. |
| `scope` | Un chemin du site : les pages qui appartiennent au site installé ; les autres s'ouvrent dans le navigateur. |
| `display` | `fullscreen`, `standalone`, `minimal-ui` ou `browser`, les modes le long desquels tous les navigateurs se replient. |
| `display_override` | Les modes demandés en premier, chacun une fois : les quatre ci-dessus, `window-controls-overlay`, `tabbed`. |
| `orientation` | Une orientation de l'API Screen Orientation. |
| `theme_color`, `background_color` | `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa` ou un mot-clé de couleur CSS. |
| `lang`, `dir` | Une balise de langue comme `fr-CA` ; `ltr`, `rtl` ou `auto`. |
| `categories` | 10 au plus, chacune une fois, des mots en minuscules comme `books` ou `events`. |
| `icons` | 30 au plus. |
| `shortcuts` | 10 au plus, chacun dans `scope`. |

## Plein écran

`display: fullscreen` ouvre le site installé sur tout l'écran, sans l'interface du navigateur — pour un écran dans une
salle, une borne ou une table de jeu ; un navigateur qui ne le peut pas se replie sur `standalone`, puis sur
`minimal-ui`. Une page hors d'un site installé entre dans le plein écran sur un geste, avec l'action `fullscreen` du
contrôleur ([JavaScript](javascript.md)).

## Icônes

| Clé | Règle |
|---|---|
| `src` | Un chemin du site à partir de sa racine, ou le chemin logique d'une ressource — `images/icon-512.png` —, donné à l'adresse que lui donne le composant Asset : avec AssetMapper, son fichier versionné. |
| `sizes` | `any`, ou jusqu'à 10 tailles comme `192x192 512x512`. |
| `type` | Le type de média d'une image, comme `image/png`. |
| `purpose` | `any`, `maskable`, `monochrome`, chacun une fois ; `any` quand c'est vide. |

Un site donne au moins une icône de 192 pixels et une de 512, et une icône `maskable` que le système d'exploitation peut
rogner.

## Raccourcis

Un raccourci a un `name` de 60 caractères au plus, une `url` dans la portée, et un `short_name` et une `description`
facultatifs ; le système d'exploitation l'offre sur l'icône du site installé.
