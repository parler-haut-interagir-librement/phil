# Routes and pages

```yaml
# config/routes/phil_directory.yaml
phil_directory:
    resource: .
    type: phil_directory
```

The loader adds seven routes for each genre, named `phil_directory_{genre}_{action}`:

| Action | Path | Methods |
|---|---|---|
| `index` | `podcasts`, `podcasts?page=2` | GET |
| `new` | `podcasts/new` | GET, POST |
| `show` | `podcasts/{slug}` | GET |
| `edit` | `podcasts/{id}/edit` | GET, POST |
| `editors` | `podcasts/{id}/editors` | GET, POST |
| `history` | `podcasts/{id}/history` | GET |
| `publish` | `podcasts/{id}/publish` | POST |

`{id}` is the UUID of the listing; the words come from `words`. The page of the editors also holds who finds the
listing ([Listings](listings.md)); the page of a listing that is not listed answers with `X-Robots-Tag: noindex,
noarchive`. The locale is the request's. A page nobody may see
answers 403, an unknown listing 404, a refused form 422.

The list of a genre shows `page_size` listings a page, by slug, with links to the previous and the next page
(`rel="prev"`, `rel="next"`); `?page=` takes a number from 1 to 999999999, and a page past the last, or anything else,
answers 404.

## Templates

The pages render `@PhilDirectory/index`, `show`, `form`, `editors` and `history`, each extending
`@PhilDirectory/layout.html.twig`, whose block `meta` in the head holds the `<meta name="robots">` of a listing that is
not listed. An application overrides them in `templates/bundles/PhilDirectoryBundle/`.

| Template | Variables |
|---|---|
| `index` | `genre`, `listings` (the page), `page` (its number), `next` (whether a page follows) |
| `show` | `genre`, `page`, `listing`, `data`, `related` (a list of `RelatedListings`), `robots` (the directives for robots, or `null`) |
| `form` | `genre`, `form`, `listing`, `id` |
| `editors` | `genre`, `form`, `listing`, `id` |
| `history` | `genre`, `listing`, `id`, `versions` |

## Translations

The labels use the domain `phil_directory`, in French and English, under `translations/`.

## Sitemap

`DirectoryUrlProvider` carries the tag `phil_sitemap.url_provider`: one section per genre, named by its key — a key
that ends in an underscore and digits, as `season_2`, with a hyphen for that underscore, `season-2`
(`Sitemap::sectionNamed()`) —, with the
list of the genre and every listing published in `sitemap_locale` with a slug, except those whose SEO hides them and
those whose author keeps them out of search engines, read in chunks of 500.
