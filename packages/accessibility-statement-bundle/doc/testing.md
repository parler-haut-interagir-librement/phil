# Testing

## The bundle's tests

The suite `tests/Unit` tests the configuration and the services it registers, the subdirectory of the recipes, the
Flex recipe, the statement of the configuration and the versions kept on SQLite in memory — their moments in UTC on a
server of another time zone —, the table and its listener, the directory of
reports, the regeneration, the publication and its refusals, the administration page refused without the role, Symfony
Security or its CSRF protection, the command, and the footer link with the functions and the filter of Twig.

The suite `tests/Functional` boots an application with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle,
TwigComponentBundle with the recipes of one kit copied where `ux:install` puts them — the PHIL UX kit in a subdirectory
of the components and at their root, or the Shadcn kit of the Symfony UX Toolkit at their root —, and Symfony Security
signing in by HTTP Basic: the page in both languages and every
wording drawn by the recipes, the versions, the footer link, the command that publishes a version from a report, and
the administration page — refused to a visitor and a reader, a forged token, a document refused then the draft
published. `KitRenderingTest` renders the page and the administration page with each kit: the same headings, list
items, header cells and labels, no prop of one kit written as an attribute by the other, no attribute twice, and no
word written by a template. Each request of the page goes through the kernel as a worker serves it, terminated, then
the services reset. The Shadcn kit is read from `symfony/ux-toolkit`, a development requirement; its tests are skipped
without it.

From the monorepo root:

```sh
vendor/bin/phpunit packages/accessibility-statement-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application

The page needs no database with the `config` source: a functional test requests `/accessibility` and reads the page.
With the `doctrine` source, the table is created from `StatementTables::configure()`. The command is tested with a
directory of reports written by the test and named by `audit.directory`.
