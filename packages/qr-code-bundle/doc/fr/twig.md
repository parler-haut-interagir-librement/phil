<!-- phil-dev source-sha256=e6ad686107f1866c3d61b1b79f4b51fb4e53b1317f6ea4c5271a0396ce827dc0 -->
# Twig

## `qr_code()`

Une fonction et un filtre, qui dessinent un code avec les valeurs par défaut de la configuration :

```twig
{{ qr_code('https://example.com/tickets/42') }}
{{ 'https://example.com/tickets/42'|qr_code({title: 'Ticket 42', level: 'H'}) }}
<img src="{{ qr_code(url, {output: 'png_data_uri', scale: 4}) }}" alt="{{ qr_code_label(url) }}">
```

| Option | |
|---|---|
| `output` | `svg` (SVG en ligne, par défaut), `data_uri` (SVG en URI `data:`) ou `png_data_uri` |
| `level` | `L`, `M`, `Q` ou `H` |
| `quiet_zone`, `dark`, `light` | comme dans la configuration |
| `size` | la largeur et la hauteur du SVG en pixels |
| `scale` | les pixels d'un module du PNG |
| `logo` | le nom d'un logo configuré |
| `title` | le nom du SVG pour les lecteurs d'écran ; le libellé du contenu quand il est vide |

Une option inconnue, ou une valeur hors de sa plage, est refusée avec `InvalidOption`. Le SVG peut être écrit tel
quel : son seul texte, le titre, est échappé, et il ne contient aucun script.

## Contenus typés

Le contenu est un texte, un contenu typé de phil/qr-code, ou le tableau de l'un d'eux :

```twig
{{ qr_code({type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}) }}
{{ qr_code({type: 'vcard', name: 'Jane Doe', phones: ['+15551234567'], emails: 'jane@example.com'}) }}
{{ qr_code({type: 'event', summary: 'Game night', start: '2026-11-13T19:00:00-05:00', location: 'Back room'}) }}
```

| Type | Clés |
|---|---|
| `url` | `url` |
| `email` | `to`, `subject`, `body`, `cc`, `bcc` |
| `phone` | `number` |
| `sms` | `number`, `message` |
| `geo` | `latitude`, `longitude`, `altitude`, `uncertainty`, `name` |
| `wifi` | `ssid`, `password`, `security` (`WPA`, `WEP` ou `nopass`), `hidden` |
| `mecard` | `name`, `phones`, `emails`, `url`, `address`, `note`, `birthday` |
| `vcard` | `name`, `family_name`, `given_name`, `organization`, `title`, `phones`, `emails`, `urls`, `address` (`street`, `locality`, `region`, `postal_code`, `country`), `birthday`, `note`, `version` (`3.0` ou `4.0`) |
| `event` | `summary`, `start`, `end`, `location`, `description`, `url`, `all_day`, `uid` |

Une liste peut être donnée sous forme d'un seul texte ; les dates s'écrivent `2026-11-08` ou
`2026-11-08T19:00:00-05:00`. Un type ou une clé inconnus, ou une valeur du mauvais type, sont refusés avec
`InvalidContent`, comme un champ que le contenu ne peut pas écrire.

## `qr_code_label()`

Ce que contient un code, en quelques mots : l'adresse, le numéro, le nom du réseau ou du contact, le résumé de
l'événement — jamais un mot de passe. Le SVG est nommé par lui, sauf si un `title` est donné.

## `qr_code_url()`

L'adresse signée de la route, quand elle est activée : voir [La route](route.md).
