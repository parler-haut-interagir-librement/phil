# Filters

The filters of a results page: a box per value of each facet, with its count; the values chosen, each a link that
removes it, and a link that removes them all; the count of results in a live region. They work as plain links and a
plain `GET` form, without JavaScript; the `filters` Stimulus controller makes them apply at once.

## The request

`SearchRequest` reads what a visitor asked from the query string: the words, `q`; the values chosen for each facet,
`filter[genre][]`; the page, `page`.

```php
use Phil\SearchBundle\Application\SearchRequest;

$asked = SearchRequest::fromQuery($request->query->all(), facets: ['genre', 'year'], perPage: 20);
$result = $search->search('games', $asked->query(['genre', 'year'], ['locale' => $request->getLocale()]));
```

| Read | Kept |
|---|---|
| `q` | Text of valid UTF-8, its first 200 characters |
| `filter[<facet>][]` | Only the facets given; text values of 1 to 200 characters, each once, 100 per facet |
| `page` | 1 to 1000, written as a number |

Anything else is left out, so that a link copied, cut or forged still opens a page. `query()` adds the filters the page
sets itself — a locale, a kind — before those a visitor chose. `without($facet, $value)`, `withoutFilters()` and
`onPage()` give other requests, and `queryString()` the link to them: `?` and the parameters, which keeps the path of
the page, without reading any address.

## The component

```twig
<twig:PhilSearchFilters
    :request="request"
    :result="result"
    :labels="{genre: 'Genre'|trans, year: 'Year'|trans}"
    :valueLabels="{genre: {jdr: 'Role-playing game'|trans}}"
    results="results"
/>

<ul id="results">
    {% for game in games %}<li>{{ game.title }}</li>{% endfor %}
</ul>
```

| Prop | What it is |
|---|---|
| `request`, `result` | The `SearchRequest` and the `SearchResult` of the page; required |
| `labels` | Each facet shown, in this order, and its name for a visitor |
| `valueLabels` | The name of a value, per facet, when it is not the value itself |
| `results` | The identifier of the element holding the results, which the controller draws again with the filters |
| `id` | The identifier of the component's root, `phil-search-filters` by default; kept from one page to the next |
| `action` | Where the form goes; the same page when empty |

A facet with no value found is not shown; a value chosen that the search no longer finds is still shown, with a count
of 0, so that it can be unticked. Each box keeps the same identifier from one page to the next. The count is said with
each value — "2 results" — and shown as a number; the links removing a filter say so to a screen reader.

The component is drawn with the recipes Badge, Button, Checkbox and Field of the PHIL UX kit, or of any kit with the
same names and props, which the application installs with the Symfony UX Toolkit:

```bash
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install checkbox --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipes are named after the components subdirectory of the Toolkit — with `ui`, `ui:Checkbox` —, or after
`phil_search.kit_subdirectory`. The texts are in the domain `phil_search`, in English and French.

## The controller

The component's root carries the controller `phil--search-bundle--filters`, which the application installs with
Symfony UX — AssetMapper or Webpack Encore — from `assets/package.json`. With it:

- the button that applies the filters is hidden, and a box applies its filter as soon as it is ticked;
- a link that removes a filter is followed in place; a click with a key held, or of another button, is left to the
  browser;
- the page is fetched, and the filters and the element named by `results` are drawn again without a reload; the title
  of the page follows;
- the live region stays in the page and gets the new count, which a screen reader says;
- the focus comes back to the box or the link that had it, else to the filters, when it was among them;
- the address changes with the filters, and going back draws the previous ones;
- a page that cannot be fetched, or an address of another origin, is opened by the browser, as it would be without the
  controller.

The controller uses `fetch`, `DOMParser` and `history.pushState`, with no inline script or style, so it runs under a
strict Content Security Policy.
