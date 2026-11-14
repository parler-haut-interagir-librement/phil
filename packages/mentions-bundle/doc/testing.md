# Testing

## The bundle's tests

`tests/Unit` covers mentions, the default pattern and the application's, the patterns refused, the handles of plain
text and of Markdown with their offsets, the marks of UJO documents, the limits, the recorder — new mentions, the same
text again, an edit, the author, visibility, the limits before the directory, the mentions of a member, a subject
forgotten —, the facade, the linker — links, spans, unsafe addresses, elements never read, the limit, invalid UTF-8 —,
the same contract on both stores (the Doctrine one on SQLite in memory, its table created from the mapping, each row
read back and the unique constraint checked) — a member already mentioned left out and the row found kept, a failed
change rolled back, the store in memory emptied between requests —, the upsert of each platform, a member another
request recorded meanwhile announced by that request only, the Flex recipe and the directory it copies, the
notification and the preferences, the Twig filter, the editor's
search, the comments' mentions and authors, the bridge on comments written, edited, over the limit and deleted,
members and authors never named by an email address, the compiler pass, and the bundle's configuration, services and prepended
settings.

`tests/Functional` boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, Twig, the notifications bundle, the
comments bundle and the bundle, records a comment twice, and checks that each member is notified once and that the
filter links them; then writes a comment on a page through the comments bundle and checks that the bridge notified the
member it mentions, and forgot the mention once the comment was deleted.
Installed on its own, where the event dispatcher is a development requirement, the functional suite is skipped.

`tests/Integration` runs the contract of the stores on the Doctrine store against MySQL 8 and PostgreSQL 17, where the
upsert is each server's own statement; it is skipped unless `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` is
set, as `phil-dev services test` sets them:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test
```

`tests/js` runs the pure modules under Node's test runner: the query before the caret, the handle inserted, the
suggestions' handles, and the request with its failures.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/mentions-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/mentions-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## Benchmarks

`tests/Benchmark/MentionsBench.php` measures the reading of Markdown and the linking on a short comment and on hostile
texts at the length limit — a hundred thousand `@`, twenty thousand handles, backticks, unclosed fences —, and the
marks of a document of fifty blocks. A comment costs a few microseconds; the hostile texts a few to a few tens of
milliseconds, growing with their length.

```sh
composer bench -- packages/mentions-bundle/tests/Benchmark
```

## Fuzzing

The target `text-mentions` reads texts nobody wrote, as plain text and as Markdown, and links them: nothing throws,
and every mention found starts with an `@` at its offset.

```sh
composer fuzz -- text-mentions --budget=60
```

## The browser suite

`tests/Browser/MentionBrowserTest.php` drives the controller in a real Chrome with `symfony/panther` and the system's
Chrome driver, on a page served by PHP's built-in server (`tests/Browser/router.php`) with Stimulus, the
controller, its stylesheet and a member search that answers as the block editor's does. It checks:

| Area | What happens |
|---|---|
| Roles | The field is a `combobox` with `aria-autocomplete="list"` and `aria-expanded`, controlling a hidden `listbox`; a `status` follows it |
| Opening | `Hi @ad` lists the two members whose handle begins with `ad`, the first `aria-selected` and named by `aria-activedescendant`, and announces *2 members found*; the focus stays in the field |
| Keys | The arrows move the active member and wrap; Enter writes `@adele` and a space in place of `@ad`, the caret after it, dispatches `chosen` with the handle and the reference, and closes the list |
| Tab | Chooses the active member rather than leaving the field |
| Escape | Closes the list; the text stays |
| Mouse | A click chooses a member; the focus stays in the field |
| Leaving | Focusing elsewhere closes the list |
| Nobody | `@zzzz` says *No member found* in the list and the live region; Enter writes nothing |
| Refused | A search the server refuses lists nobody and fails nothing |
| Not a mention | `ada@ex` opens nothing |

It runs apart from `composer test`, with the other browser suites, after installing its Stimulus:

```sh
composer test:browser
```

The filter on a saved comment, which links `@ada` to the profile, is covered by the unit and functional suites.
