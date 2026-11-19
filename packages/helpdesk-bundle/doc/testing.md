# Testing

## The bundle's tests

The suite `tests/Unit` tests the name of a queue, the request and its moments, the statuses and the failures; the
queues of the configuration and the workflow; `Helpdesk` over the DBAL stores of the help desk and of the conversations
on SQLite — opening, the requester's requests and answers, an agent's answer and its assignment, the queues an agent
works, the desk and its filters, assigning, moving, the manual transitions, a guard of the application, a closed request,
canned replies —; the store, its moments in UTC, its pages and the erasure, the schema listener; the access by role,
the canned replies of the templates, the export and the erasure, the notification of an assignment, the Twig functions
and the link of an EasyAdmin dashboard; and every definition the configuration wires.

The suite `tests/Functional` boots a kernel with FrameworkBundle, Symfony Security with members in memory,
DoctrineBundle on SQLite, the KeyManagement component, TwigBundle and the recipes of the kit, an EasyAdmin dashboard,
and phil/conversation-bundle, and serves the pages: a request from the member to the agent and back, with a canned
reply, resolved and closed; an agent taking, moving and filtering; the requests of others not found, the desk denied to
a member who works no queue, the forms without a token refused; the desk grafted on the dashboard.

The suite `tests/Integration` runs a request through the desk on MySQL 8.4 and PostgreSQL 17, at `PHIL_TEST_MYSQL_URL`
and `PHIL_TEST_POSTGRESQL_URL`, and is skipped without them.

From the monorepo root:

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/helpdesk-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses the help desk

In a kernel test, sign in as a member, then as an agent of a queue, and follow a request through the pages:

```php
$client->loginUser($member);
$client->request('POST', '/help/new', ['_token' => 'csrf-token', 'queue' => 'billing', 'subject' => 'Help', 'body' => 'Please.'], server: ['HTTP_ORIGIN' => 'http://localhost']);

$client->loginUser($agent);
$client->request('GET', '/support-desk/');
```

With the stateless CSRF protection of FrameworkBundle in a test environment, the value `csrf-token` passes when the
request names the site as its origin.
