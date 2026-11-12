# Testing

| Area | What is covered |
|---|---|
| `Listing` | The genre and the author set once, the editors and their bounds, who may edit, an erased user forgotten |
| The five pipelines | Mapping, merging and normalizing the contact, the address, the location, the related listings and the editor, half a position |
| Relations | `Relations` by key and by genre, a relation named like a genre, the titles of `RelatedListings`, the shape of `related` refused when it is not lists by relation |
| Configuration and services | The tree, the defaults, the services, what is prepended and when |
| `DirectoryRoutes` | The routes of each genre, their names, methods and requirements |
| `ListingVoter`, `Actors` | Each attribute for the author, an editor, a stranger, an administrator and nobody |
| A directory in an application | Writing, editing as an editor, the draft kept, publishing, the history, an old slug redirected, the sitemap, a slug already taken, the pages of a genre, a listing without a slug left out, related listings on both sides and their checks, the listings that name one looked up among the live rows, an erased user, a listing kept out of search engines then unlisted: the sitemap, the list of its genre, the related listings, its robots directives and who may decide |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on a SQLite file, TwigBundle, content-bundle
and the bundle, with the security services built by hand: `DirectoryBundleTest` signs in by setting a token and drives
every page through the browser kit.

```sh
vendor/bin/phpunit packages/directory-bundle/tests
```

## Benchmarks

`DirectoryBench` boots the functional application on SQLite in memory and reads the related listings and the
published listings of a genre, with 10, 100 and 500 larps. From the monorepo root:

```sh
composer bench -- packages/directory-bundle/tests/Benchmark
```
