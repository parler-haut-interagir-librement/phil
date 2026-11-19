# The attribution page

`AttributionPage::of($declaration, $catalog)` gathers what the page shows: the license, the notice, the declarations,
the resources — the text sources first, then the images, the maps, the fonts and the others, each kind in the order of
the declaration —, every copyright notice once, the work's first, and every license the page names once.

## HTML

`AttributionRenderer::html($subject, $locale)` renders the template — a `<section class="phil-licensing">` without a
document around it — for a page of the site or a book laid out from HTML. The subject is a `SubjectReference`, a
`Licensable` or a declaration; a subject without a declaration gives an empty string. Every text is escaped, line
breaks kept.

| Section | Shown when |
|---|---|
| License, with its address and reference | always; an unknown code says so |
| Content offered under the license, Reserved Material, Product Identity | the declaration holds it |
| Attributions, by kind | there are resources |
| Copyright notices | the work or a resource has one |
| License text | the declaration holds it |

## Twig

| Function | Returns |
|---|---|
| `phil_licensing_attribution(subject, locale = null)` | The HTML fragment. |
| `phil_licensing_page(subject)` | The `AttributionPage`, or `null`, for a template of the application. |
| `phil_licensing_license_name(license, locale = null)` | The name of a license or a code, translated. |
| `phil_licensing_violation(violation, locale = null)` | The message of a violation. |

```twig
<article>{{ phil_licensing_attribution(book, app.request.locale) }}</article>
```

The texts are the messages `page.*`, `kind.*` and `resource.*` of the domain `phil_licensing`.

## UJO

With `phil/ujo`, `AttributionDocument::of($page, $locale)` gives the same page as a UJO (Universal JSON Output)
document of `header`, `paragraph` and `list` blocks, identified `phil-licensing-1`, `phil-licensing-2` and so on: a
book laid out from blocks appends it. Every text is escaped; the addresses are links.

## JSON-LD

With `phil/structured-data`, `LicenseStructuredData::properties($page)` gives `license` — the address of the work's
license —, `copyrightNotice` and `isBasedOn`, each resource as a `CreativeWork`, `ImageObject` or `Map` with its
`name`, `author`, `url`, `license` and `copyrightNotice`, to merge into the application's node of the work;
`node($page, 'Book', $id)` gives a node of their own.
