# Placeholders

A body names values between `${` and `}`. `LegalNotices::render()` renders the body as HTML first, then
replaces each placeholder of its text that has a value with that value, as text — escaped for HTML, its line breaks
kept as `<br>`. A placeholder without a value is left as written; `LegalNotices::check()` reports it.

| Placeholder | Value |
|---|---|
| `${publisher_name}`, `${host_address}`… | The field of the identity of the same name |
| `${version}` | The number of the version |
| `${effective_date}` | Its effective date, `Y-m-d` |
| `${retention.<id>}` | The period of a retention, in words in the locale |
| `${consent.<key>}` | The name of a consent category in the locale |

A name is a lowercase letter, then lowercase letters, digits and `_`, 64 characters at most, maybe followed by a `.`
and an identifier.

## Why `${…}`

The marker has to reach the HTML as written, whatever format of phil/markup the body is written in, since the values
are filled in once the body is rendered. Each format reserves some brackets:

| Marker | Read by |
|---|---|
| `[[…]]` | MediaWiki, DokuWiki and Creole: an internal link |
| `{{…}}` | MediaWiki: a template; DokuWiki and Creole: an image |
| `{{{…}}}` | Creole: preformatted text |
| `<<…>>` | Creole: a macro |
| `%%…%%` | DokuWiki: text kept unformatted |
| `{…}` | Markdown: attributes, or ruby with a vertical bar |
| `[…]` | BBCode: a tag; Markdown: a link text |
| `%name%` | The parameters of a Symfony container, in the configuration that holds the bodies |

No format gives `${…}` a meaning: it passes through plain text, Markdown, HTML, BBCode, MediaWiki, DokuWiki, Creole
and UJO (Universal JSON Output) unchanged, so a body names placeholders in any of them. In a PHP string between double
quotes, write `\${name}`, or use single quotes.

```text
This site is published by ${publisher_name}, ${publisher_address}.
We keep your account for ${retention.R-001}.
```

`Placeholders::names($text)` lists the names a text gives, each once; `Placeholders::of()` builds the values of a
version in a locale; `fill($html)` replaces them, each value once, so that a value is never read again as a
placeholder.

## Text only

`fill()` reads the rendered HTML with the HTML5 parser of the DOM extension, as the content of a `<body>`, fills the
placeholders of its text nodes only and writes the HTML back with the HTML serializer. A placeholder written anywhere
else is left as written:

- in a tag: an `href`, a `src`, a `style` or any other attribute, where a value such as `javascript:alert(1)` would
  make a link that runs a script, whatever its escaping;
- in a comment or a `<template>`;
- in the text of an element whose text the serializer writes as it is: `<script>`, `<style>`, `<iframe>`,
  `<noembed>`, `<noframes>`, `<noscript>`, `<plaintext>` and `<xmp>`, where a value could close the element.

`misplaced($html)` lists the names that have a value but are left this way, and `LegalNotices::check()` reports each
one as `misplaced_placeholder`. A link to the site is written in the body with its address, never with a placeholder:

```html
<!-- Left as written, and reported -->
<a href="${site_name}">${site_name}</a>

<!-- Filled -->
<a href="https://example.com/">${site_name}</a>
```

An HTML without a placeholder to fill is returned unchanged; one with a placeholder to fill comes back as the
serializer writes it, `&apos;` as `'` for instance.
