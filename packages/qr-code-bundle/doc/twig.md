# Twig

## `qr_code()`

A function and a filter, which draw a code with the defaults of the configuration:

```twig
{{ qr_code('https://example.com/tickets/42') }}
{{ 'https://example.com/tickets/42'|qr_code({title: 'Ticket 42', level: 'H'}) }}
<img src="{{ qr_code(url, {output: 'png_data_uri', scale: 4}) }}" alt="{{ qr_code_label(url) }}">
```

| Option | |
|---|---|
| `output` | `svg` (inline SVG, the default), `data_uri` (SVG as a `data:` URI) or `png_data_uri` |
| `level` | `L`, `M`, `Q` or `H` |
| `quiet_zone`, `dark`, `light` | as in the configuration |
| `size` | the width and height of the SVG in pixels |
| `scale` | the pixels a module of the PNG |
| `logo` | the name of a configured logo |
| `title` | the name of the SVG for screen readers; the content's label when empty |

An unknown option, or a value out of its range, is refused with `InvalidOption`. The SVG is safe to write as it is:
its only text, the title, is escaped, and it holds no script.

## Typed contents

The content is a text, a typed content of phil/qr-code, or the array of one:

```twig
{{ qr_code({type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}) }}
{{ qr_code({type: 'vcard', name: 'Jane Doe', phones: ['+15551234567'], emails: 'jane@example.com'}) }}
{{ qr_code({type: 'event', summary: 'Game night', start: '2026-11-13T19:00:00-05:00', location: 'Back room'}) }}
```

| Type | Keys |
|---|---|
| `url` | `url` |
| `email` | `to`, `subject`, `body`, `cc`, `bcc` |
| `phone` | `number` |
| `sms` | `number`, `message` |
| `geo` | `latitude`, `longitude`, `altitude`, `uncertainty`, `name` |
| `wifi` | `ssid`, `password`, `security` (`WPA`, `WEP` or `nopass`), `hidden` |
| `mecard` | `name`, `phones`, `emails`, `url`, `address`, `note`, `birthday` |
| `vcard` | `name`, `family_name`, `given_name`, `organization`, `title`, `phones`, `emails`, `urls`, `address` (`street`, `locality`, `region`, `postal_code`, `country`), `birthday`, `note`, `version` (`3.0` or `4.0`) |
| `event` | `summary`, `start`, `end`, `location`, `description`, `url`, `all_day`, `uid` |

A list may be given as one text; dates are written as `2026-11-08` or `2026-11-08T19:00:00-05:00`. An unknown type or
key, or a value of the wrong type, is refused with `InvalidContent`, as is a field the content cannot write.

## `qr_code_label()`

What a code holds, in a few words: the address, the number, the name of the network or of the contact, the summary of
the event — never a password. The SVG is named by it unless a `title` is given.

## `qr_code_url()`

The signed address of the route, when it is enabled: see [The route](route.md).
