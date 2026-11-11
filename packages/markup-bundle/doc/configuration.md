# Configuration

```yaml
phil_markup:
    formats: [markdown, bbcode, text] # every format by default
    default_format: ~                 # the first enabled format by default
    default_level: all                # basic, forum or all
    levels:                           # the highest level of a format; all for a format not named
        bbcode: forum
    added_formats:                    # the formats add-ons bring, by name; an add-on bundle prepends its own
        shout: { label: Shout, reader: app.shout_reader }
    smart_punctuation:
        enabled: true                 # when phil/typography is installed
        locale: ~                     # the locale of the request by default
    media_hosts: [cdn.example.com]    # null by default: any host
```

## Formats

`formats` lists the formats texts may be written in, by name — `markdown`, `html`, `bbcode`, `mediawiki`,
`dokuwiki`, `creole`, `text`, `ujo`, and those of `added_formats` —, in the order `MarkupFormatType` offers them; empty
or absent, every format, the added ones last. A name no format has is refused when the container is built.
A format named twice counts once, and the list of a later configuration file replaces that of an earlier one rather
than adding to it. A template, the converter or a form asking for a format not listed gets an
[`UnavailableFormat`](exceptions.md).

`default_format` is the format taken when a filter or the converter names none: the first enabled format when it is
not given. A default format not enabled is refused when the container is built.

## Formats of add-ons

`added_formats` declares the formats add-ons bring, each by its name: `label`, what a form shows, translated in the
`phil_markup` domain, and `reader`, the identifier of the service that implements `phil/markup`'s `ReaderProvider`.
`DefaultCompiler` reads the format with the reader the service gives, and every part of the bundle — the filters, the
converter, the form, the levels — takes its name as a built-in one. An add-on bundle declares its own format from its
`prependExtension()`, so that installing the add-on is enough:

```php
$builder->prependExtensionConfig('phil_markup', ['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => ShoutReaders::class]]]);
```

A name is 1 to 16 lower-case letters, digits and hyphens, starts with a letter and is not a built-in format, so that it
fits the column of a `MarkedUpText`; any other is refused when the container is built. The entries of several files
add up.

## Levels

`default_level` is the level the Twig filters purify at, and the converter reads at, when none is named.

`levels` gives, format by format, the highest level a text in that format is purified at, whatever a template asks:
with `bbcode: forum`, `{{ post|markup_html('bbcode', 'all') }}` purifies at `forum`, so a member's BBCode keeps no
heading, table or raw HTML. A format not named goes up to `all`. The ceiling also applies to `markup_purify` given a
`MarkedUpText`, by the format the text was written in, and to the converter, which reads raw HTML into a `raw` block
only at `all`. A level that is not `basic`, `forum` or `all`, or a format that is not one, is refused.

## Smart punctuation

When `phil/typography` is installed, the HTML of every format goes through a typographer before it is purified:
typographic quotes, dashes, ellipses and the spaces the locale puts around punctuation, as the preset of the locale
defines them.

```twig
{{ '"Oui" ! Voilà...'|markup_html('markdown') }}
{# <p>« Oui » ! Voilà…</p>, in a French request #}
```

The typographer rewrites text only: the content of `pre`, `code`, `kbd`, `script`, `style` and `textarea` keeps its
characters, and the purifier still runs afterwards, so the HTML is as safe as without it. It works on the compiler
behind the `Phil\Markup\MarkupCompiler` alias, which `phil_markup.smart_punctuation` decorates: `Markup`, the filters,
a `MarkedUpText` built by `Markup::text()` and `phil/content-bundle` get it, and so does a compiler the application
puts behind the alias. `UjoConverter` reads the source itself, and keeps its characters.

| Option | Default | Effect |
|---|---|---|
| `smart_punctuation.enabled` | `true` | `false`, or `smart_punctuation: false`, leaves the HTML of the compiler as it is |
| `smart_punctuation.locale` | `null` | The locale of the fixes; when null, the current locale of Symfony's `LocaleSwitcher` — the request's, or the one `runWithLocale()` sets —, and the default locale of the typographers without the translator |

The typographers are those of `phil/typography-bundle` when it is installed, with the locales, fixers and
hyphenation configured there. Otherwise the bundle builds its own, `phil_markup.typographers`: the preset of each
locale, the kernel's default locale by default, without soft hyphens. When `phil/typography-bundle` also fixes the
HTML of UJO documents, which it does through the same `Phil\Ujo\Html\HtmlRenderer`, the formats rendered through UJO
are left to it, in the locale of its `ujo` option, and the smart punctuation fixes HTML and plain text only: no text is
fixed twice.

## Image hosts

`media_hosts` is the list of hosts images may come from, subdomains included, besides the site itself; `[]` keeps
the site's own images only, and `null`, the default, any host. An image of another host makes every reader's browser
call it, which learns their address: a site whose members write texts at `forum` sets it. An image from a host not
listed loses its `src`. The hosts reach `SanitizerPurifier` whichever bundle registers it, `phil/content-bundle`
included, through a compiler pass; a purifier the application builds itself is left alone.

## Replacing the compiler or the purifier

`Phil\Markup\MarkupCompiler` and `Phil\Markup\HtmlPurifier` are aliases; an application points either at its own
service, and `Markup`, the filters and `phil/content-bundle` follow:

```php
#[AsAlias(HtmlPurifier::class)]
final readonly class StrictPurifier implements HtmlPurifier
{
    public function purify(string $html, Level $level): string { /* … */ }
}
```

The renderer `Phil\Ujo\Html\HtmlRenderer`, which `DefaultCompiler` renders UJO with, is a service too, built by
`HtmlRenderer::default()`; `phil/ujo-bundle` registers the same one. The converter reads with `DefaultCompiler`
itself, whatever `MarkupCompiler` points at: an application's own compiler changes the HTML, not the readers.
