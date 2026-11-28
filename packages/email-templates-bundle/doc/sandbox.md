# The sandbox and the components

## What a template may write

Every source of a template — subject, preheader, body, plain-text part — renders in a Twig environment of its own,
sandboxed with Twig's `Sandbox` in strict mode: what the allow-list does not name is refused, at the save that checks it
and at every render. The application's own Twig, its globals, its extensions and its templates are out of reach.

| Kind | Allowed |
|---|---|
| Tags | `if`, `for`, `set`, `apply` |
| Filters | `abs`, `capitalize`, `date`, `default`, `e`, `escape`, `first`, `format`, `join`, `last`, `length`, `lower`, `nl2br`, `number_format`, `replace`, `reverse`, `round`, `slice`, `split`, `striptags`, `title`, `trim`, `upper`, `url_encode`, `panel`, `footer`; with `twig/intl-extra`, `format_date`, `format_datetime`, `format_time`, `format_number`, `format_currency` |
| Functions | `button`, `table`, `image`, `segment`, `max`, `min` |
| Tests | `defined`, `empty`, `even`, `odd`, `null`, `none`, `same as`, `divisible by`, `iterable`, `sequence`, `mapping`, `true` |
| Methods | `format()` and `getTimestamp()` of a date |
| Properties | none |

Refused among the rest: `include`, `extends`, `embed`, `import`, `macro`, `block`, `source`, `attribute`, `constant`,
`range` and `..`, `raw`, `map`, `filter` and `reduce`. The application allows the filters and functions of its own Twig
extensions by tagging them `phil_email_templates.sandbox_extension` and listing their names in `sandbox.filters` and
`sandbox.functions`.

A body is escaped as HTML; a subject, a preheader and a plain-text part are rendered as text, then the subject and the
preheader are folded on one line. Variables are strict: reading a name the context lacks is an error, and a save
reports every variable the sources read that the schema does not declare. A template reads the render itself as
`email.key`, `email.site`, `email.locale` and `email.segments`.

Dates: the `date` filter converts to the time zone of PHP unless given `false` —
`{{ starts_at|date('Y-m-d H:i', false) }}` keeps the offset of the value. The Intl filters take the locale as an
argument: `{{ total|format_currency('CAD', locale=email.locale) }}`.

## The limits

| Limit | Value |
|---|---|
| Loops nested | 2 deep — a list holds at most 1000 values, so a template walks at most a million |
| Output of one source | `max_html_bytes`, 1 MiB by default |
| The whole document | `max_html_bytes` |
| A source | 2000 characters for the subject and the preheader, 256 KiB for the body and the plain text |

## The components

The components draw with tables and inline styles, which the email clients render alike, Outlook's included; each
carries the class the dark mode of the layout restyles. In the subject, the preheader and the plain-text part they write
text instead.

| Component | Draws | In text |
|---|---|---|
| `button(label, url, {align})` | a button drawn by a table cell; `url` an absolute `http`, `https` or `mailto` address; `align` `left`, `center` or `right` | `label: address` |
| `table(rows, {header, caption})` | a data table: the header cells, then each row of cells, every cell escaped | the rows, cells separated by `\|` |
| `image(src, alt, {width})` | an image: `cid:<name>` of the template's `embeds`, or an `https` address; `width` from 1 to 1200 | `[alt]` |
| `{% apply panel %}…{% endapply %}` | a panel on the panel colour | the content |
| `{% apply footer %}…{% endapply %}` | the small, muted footer of the message | the content |
| `segment(name)` | whether the recipient is in a segment the template declares | |

```twig
<h1>Here is your ticket, {{ recipient.name }}</h1>
{{ table([['Ticket', ticket.number], ['When', event.starts_at|date('l, F j, Y \\a\\t g:i A', false)]]) }}
{{ button('Show my ticket', ticket_url, {align: 'center'}) }}
{% apply footer %}<p style="margin:0;">You receive this email because a ticket was issued in your name.</p>{% endapply %}
```

## Markdown

A body in Markdown is rendered by Twig first, its variables escaped as HTML, then read by `phil/markup` — CommonMark and
the GitHub extensions — and purified at its `all` level: headings, emphasis, links, lists, quotes, tables and images,
nothing a sanitizer counts as unsafe. The bundle uses `phil/markup` rather than Twig's `markdown_to_html`, which needs a
Markdown library of its own, and so that the purification is the one every PHIL package applies.

While Markdown is read, each component is kept aside behind a placeholder of letters and digits, which Markdown leaves
alone, then put back whole once the Markdown is HTML: a button, a table, a panel or a footer becomes a block of its own;
an image stays in its line. The content of a panel or a footer is Markdown too.
