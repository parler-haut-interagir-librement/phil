# Twig

The filters are registered when Twig is installed.

| Filter | Input | Output |
|---|---|---|
| `typography(locale = null)` | HTML | Fixed HTML, marked safe |
| `typography_text(locale = null)` | Plain text | Fixed text, escaped as usual by Twig |

Without a locale, both use the current locale — that of the request — and give an empty string for `null`.

## Escaping

`typography` escapes its input first unless it is marked safe, then fixes it as HTML. A string from a visitor stays
text: its `<script>` is shown, not run. HTML the application trusts is marked safe before the filter — with `|raw`,
or because a filter such as `ujo_html` or `markup_html` returned it safe — and keeps its markup:

```twig
{{ comment.text|typography }}                 {# escaped, then fixed #}
{{ article.bodyHtml|raw|typography }}         {# trusted HTML, fixed #}
{{ article.body|ujo_html|typography('fr') }}  {# safe output of another filter #}
```

The fixers only rewrite text, so what `typography` returns is as safe as what it received.

`typography_text` treats its input as plain text — tags are characters — and its output is escaped by Twig like
any string; use it for titles, attributes and other places where markup has no meaning:

```twig
<title>{{ page.title|typography_text }}</title>
<img alt="{{ image.caption|typography_text }}" src="…">
```

## The locale of a template

Without a locale, the filters ask Symfony's `LocaleSwitcher` for the current one: the locale of the request, as the
router and `LocaleListener` set it, or the locale `LocaleSwitcher::runWithLocale()` switches to, in a command or a
message handler that renders an e-mail in its reader's language. Outside a request, the switcher gives the kernel's
default locale. Without the translator, `symfony/translation`, there is no switcher and the filters use
`default_locale`.

```twig
{{ article.body|raw|typography }}   {# the locale of the request #}
```

```php
$html = $localeSwitcher->runWithLocale('de_CH', fn (): string => $twig->render('mail.html.twig', $context));
```

A text whose language is not the page's passes its own, which always wins:

```twig
<blockquote lang="en-GB">{{ quote.text|typography_text('en_GB') }}</blockquote>
```
