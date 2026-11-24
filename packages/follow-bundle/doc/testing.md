# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs the bundle on SQLite in memory, its tables created from their definition, its
statements counted, with the notifications of phil/notifications-bundle handing their messages to a recording bus. It
proves, among others, that an activity reaches 2 400 followers in a bounded number of statements, that a retried
message sends to no one twice, that ignoring a subject wins over watching its parent, that a locked profile takes a
request, and that stopping to follow a member keeps the mutual relationship.

The functional suite, under `tests/Functional`, boots an application with FrameworkBundle, DoctrineBundle and the ORM on
a SQLite file, Twig and phil/notifications-bundle, creates the tables from the ORM's schema, and posts the forms of the
templates as a browser without JavaScript does. In the monorepo, Infection holds the package at 100 % of covered
mutants.
