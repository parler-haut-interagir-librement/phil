# Serving and dumping

## Served

`/sitemap.xml` is the index: one entry per file, at its `/sitemap.{name}.xml` address on the host of the request,
with the latest `lastmod` of its pages. `/sitemap.{name}.xml` builds only the section the file belongs to — `news`
for `news` and for `news_1` — and answers its pages; a file the sitemap does not have is a `404`. Both are
`application/xml`.

Each answer carries what lets a cache keep it:

| Header | Value |
|---|---|
| `Cache-Control` | `public, max-age=<cache_max_age>`; private and kept by no cache when `cache_max_age` is `0` |
| `Last-Modified` | the latest `lastmod` of the pages it lists, when one has a `lastmod` |
| `ETag` | a hash of the XML |

A request that already holds the answer — `If-None-Match` or `If-Modified-Since` — gets a `304` without a body. A
reverse proxy or a CDN in front of the site then answers most crawls without the sitemap being built.

Building is what costs: the index asks every provider for every page, a file every provider of its section, and the
pages of the routes read the whole route collection, which the router loads from its resources on each request.
The router keeps the collection once loaded, for as long as it lives, so `RouteUrlProvider` asks it each time and
keeps no copy of its own: a long-running worker loads it once.
**A site in production with more than a few hundred pages dumps its sitemap** and lets the web server serve the files,
leaving the routes unimported or as a fallback.

## Dumped

```bash
bin/console phil:sitemap:dump                          # into dump_directory
bin/console phil:sitemap:dump var/sitemap --gzip
bin/console phil:sitemap:dump --base-url=https://cdn.example.com
```

| Written | |
|---|---|
| `sitemap.{name}.xml` | one per file; `sitemap.{name}.xml.gz` with `--gzip` |
| `sitemap.xml` | the index, never gzipped, pointing at the files under the base URL |

The base URL is the router's — `framework.router.default_uri` — unless `--base-url` gives another. On the command
line the router knows no host of its own: **without `default_uri` and without `--base-url`, the command refuses to
dump**, since the index would send search engines to `http://localhost`.

Nothing is written until the sitemap is built, so a provider that fails — a database down, a bad route option — leaves
the previous dump whole. Each file is then written under a temporary name beside its place and renamed into it, the
index last: a crawler never reads half a file, nor an index naming a file not there yet. A write that fails — a full
disk, a directory not writable — stops the command with an error, removes the temporary files and keeps the previous
index. Last, the `sitemap.*.xml` and `sitemap.*.xml.gz` files of an earlier dump the new one does not have are
removed, so a section gone leaves no file behind; other files of the directory stay. A deployment runs the dump once
the release is in place.
