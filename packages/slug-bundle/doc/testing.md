# Testing

| Area | What is covered |
|---|---|
| `PhilSlugBundle`, `SluggerServices` | The default slugger, named sluggers and their options, the aliases by argument name, `Sluggers`, every refused configuration |
| `SlugExtension` | The default and a named slugger, an empty slug for nothing to slug and for `null`, an unknown name |
| A small application (functional) | Autowiring by name, the application's decorator of a named slugger, the filter in a template |
| `SlugHistoryKeys` | Every accepted and refused scope and subject, at and past their limits, invalid UTF-8 |
| The history in an application (functional) | The table in the ORM schema, a change and its lookup, no chain after several changes, a slug current again, a former slug taken by another subject, `forget()`, a stored value that is not a slug, a bad scope or subject refused before any query |

```sh
vendor/bin/phpunit packages/slug-bundle/tests
```

The functional tests boot a kernel and need `symfony/framework-bundle`, `symfony/twig-bundle` and, for the history,
`doctrine/doctrine-bundle`, installed for development only. The history test runs on SQLite (a file in the kernel's
cache directory). Set `PHIL_SLUG_TEST_DATABASE_URL` to run it on another server:

```sh
PHIL_SLUG_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/slugs' vendor/bin/phpunit packages/slug-bundle/tests/Functional
PHIL_SLUG_TEST_DATABASE_URL='postgresql://app:secret@127.0.0.1:5432/app_test?serverVersion=17' vendor/bin/phpunit packages/slug-bundle/tests/Functional
```
