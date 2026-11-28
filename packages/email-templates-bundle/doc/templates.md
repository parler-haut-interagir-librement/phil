# Templates

A template is named by a **key** — 1 to 64 lowercase ASCII letters, digits, `_`, `.` or `-`, starting with a letter,
as `ticket` or `order.confirmation` — and exists in one or more **locales**: `en`, `fr`, `fr_CA` (`fr-CA` is read as
`fr_CA`). A site may only save a template whose key a default file declares, so that a site never sends an email the
application does not.

## The default files

A default file is `<key>.<locale>.yaml` in one of the directories of `defaults_paths`, then in the bundle's `defaults/`,
the first directory that has the file winning: `email_templates/ticket.fr.yaml` replaces the bundle's `ticket.fr.yaml`,
and `email_templates/welcome.en.yaml` adds a template. A file is at most 1 MiB; it is read and checked the first time
its template is asked for, and `bin/console phil:email-templates:check` checks them all, as a save would.

The bundle ships three templates, in English and in French, generic enough for any site:

| Key | For | Variables | Segments |
|---|---|---|---|
| `confirmation` | a registration, a booking or an order confirmed | `recipient.name`, `item.title`, `item.starts_at`, `item.location`, `reference`, `manage_url` | `first-time` |
| `ticket` | a ticket to show at the door | `recipient.name`, `event.title`, `event.starts_at`, `event.venue`, `ticket.number`, `ticket.category`, `ticket.seat`, `ticket_url` | |
| `cancellation` | a date, a booking or an order cancelled | `recipient.name`, `item.title`, `item.starts_at`, `reason`, `contact_url` | `refunded` |

## The fields of a template

```yaml
subject: 'Your ticket for {{ event.title }}'
preheader: 'Ticket {{ ticket.number }}: show it at the door.'
format: markdown                 # html, the default, or markdown
body: |
    Hello {{ recipient.name }},

    {{ button('Show my ticket', ticket_url) }}
text: null                       # the plain-text part; written from the HTML when null or empty
segments: [vip, first-time]
embeds: { logo: logo.png }       # shown as cid:logo, read from embeds.directory
variables:
    recipient:
        type: object
        required: true
        fields:
            name: { type: string, required: true, max_length: 200, sample: 'Ada' }
    ticket_url: { type: url, required: true, sample: 'https://example.com/tickets/42' }
```

| Field | Is |
|---|---|
| `subject` | One line of 1 to 2000 characters of sandboxed Twig, rendered as text. |
| `preheader` | At most 2000 characters, rendered as text: the line most inboxes show after the subject. |
| `format` | `html`, a body of HTML and components, or `markdown`, Markdown turned into HTML once the variables are in. |
| `body` | 1 byte to 256 KiB of sandboxed Twig, escaped as HTML. |
| `text` | At most 256 KiB, rendered as text; empty, the plain-text part is written from the HTML. |
| `segments` | At most 32 names, a lowercase letter then up to 31 lowercase letters, digits, `_` or `-`. |
| `embeds` | At most 16 images, each a content identifier of 1 to 64 letters, digits, `_` or `-`, and a relative path of letters, digits, `_`, `.`, `-` and `/`, never `..`. |
| `variables` | The schema of the variables, below. |

`TemplateDefinition::fromArray()` reads these fields and lists every reason it refuses them at once, in
`InvalidTemplate::$errors`; `toArray()` writes them back.

## The variable schema

The schema declares the named slots a template reads. Each is a map with its `type` and, optionally:

| Key | Is |
|---|---|
| `required` | `true` when a render must give it; `false` by default. |
| `default` | The value taken when none is given; it must match the type. |
| `sample` | The value the preview and the checks render with; the default, else one made up for the type, when absent. |
| `description` | A text of at most 500 characters, for the people who edit the template. |
| `fields` | For an `object`, the variables it holds. |
| `items` | For a `list`, the declaration of its values. |
| `max_items` | For a `list`, 1 to 1000; 100 by default. |
| `max_length` | For a `string`, 1 to 100 000 characters; 10 000 by default. |

| Type | Accepts | Becomes |
|---|---|---|
| `string` | a text, a number or a `Stringable` | a text of valid UTF-8 |
| `integer` | an integer, or a text of one | an integer |
| `number` | an integer, a float, or a text of one | an integer or a float |
| `boolean` | `true` or `false` | the same |
| `datetime` | a `DateTimeInterface`, or an ISO 8601 text — `2026-11-18`, `2026-11-18T19:30`, `2026-11-18T19:30:00-05:00` | a `DateTimeImmutable` |
| `url` | an absolute `http` or `https` address, read as a browser does by `phil/url` | its normalized text |
| `email` | an email address, read by `phil/email-address` | its normalized text |
| `list` | a list of values of the type `items` | a list |
| `object` | a map of the variables `fields` | a map |

A schema holds at most 100 variables, counted at every depth, nested at most 3 deep. A name is a lowercase letter or
`_`, then up to 63 lowercase letters, digits or `_`; `email`, `loop` and the names Twig keeps for itself are reserved.

`VariableSchema::validate()` checks the values of a render: each declared variable is present afterwards, given,
defaulted or `null`; a value not declared, missing while required, or not of its type is refused with
`InvalidVariables`, whose `violations` name each path — `order.lines.2.qty` — and never repeat the value, which may be
personal. `samples()` gives the sample values.

## Segments and attributes

A conditional block tests the recipient's segments with `segment()`, or any variable — an attribute — with `if`:

```twig
{% if segment('vip') %}{% apply panel %}Thank you for being a member.{% endapply %}{% endif %}
{% if order.total > 100 %}Shipping is free.{% endif %}
```

A segment must be one the template declares, or the render fails; the segments a render is given that the template
does not declare are ignored. The preview draws each variant: no segment, each segment alone, then all of them.

## Embedded images

An image the template declares in `embeds` is read from `embeds.directory` and attached to the email inline; the body
shows it with `image('cid:logo', 'Shop')`. See [Rendering and sending](rendering.md#embedded-images).
