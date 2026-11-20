# Testing

| Area | What is covered |
|---|---|
| `Listing` | The genre and the author set once, the editors and their bounds, who may edit, an erased user forgotten |
| The five pipelines | Mapping, merging and normalizing the contact, the address, the location, the related listings and the editor, half a position |
| Relations | `Relations` by key and by genre, a relation named like a genre, the titles of `RelatedListings`, the shape of `related` refused when it is not lists by relation |
| `BinaryKeysListener` | `genre`, `author` and `edited_by` in `utf8mb4_bin` in the schema generated for MySQL and MariaDB with the mapping listener of content-bundle, the default collation on SQLite, where values that differ only by case or accent are four rows, the options a field already has kept, `editedBy` left alone when it is not mapped, another entity left as it is, and the listener run after the mapping listener in the test kernel |
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

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: the listings of the
authors `ann`, `Ann`, `ANN` and `ánn`, in genres of the same names, and the versions they wrote, are each found by
their own author, genre and writer only, and on MySQL the three columns are in `utf8mb4_bin` and the tables of the
previous version reach it through the schema diff. Each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped
without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/directory-bundle/tests/Integration
```

## Benchmarks

`DirectoryBench` boots the functional application on SQLite in memory and reads the related listings and the
published listings of a genre, with 10, 100 and 500 larps. From the monorepo root:

```sh
composer bench -- packages/directory-bundle/tests/Benchmark
```
