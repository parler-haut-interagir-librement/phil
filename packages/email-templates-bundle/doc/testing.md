# Testing

## The bundle's tests

The suite `tests/Unit` tests the keys, sites and locales, the definition of a template and every reason it is refused,
the variable schema — each type, defaults, samples, nested paths, limits —, the sandbox — what it allows, what it
refuses at the save and at the render, escaping, Markdown and its placeholders, the limits, the cache, the reset —, each
component in HTML, Markdown and text, the plain text written from HTML, the resolver of templates locale by locale,
`EmailRenderer`, `EmailComposer`, `EmailTemplates` and `TemplateValidator` — saves, publications, reverts, discards,
resets, events, authors, conflicts —, the preview of what is sent, of a draft and of a version, the default files of the
bundle, each checked as a save would, the images, the layout and the inliner, the tenancy and the audit, the message and
its handler, the three commands, the contract of every store run in memory, the field values a template is kept in, the
collation of the keys of a root on each platform, and the configuration and wiring.

The suite `tests/Functional` boots an application — FrameworkBundle, DoctrineBundle on SQLite, PhilContentBundle,
SecurityBundle, TwigBundle, TwigComponentBundle with the recipes copied as `ux:install` would —, runs the contract of the
stores on the contents — with the locales the contents serve, a row written around the bundle, a second application
saving over the same revision and a long body —, and drives the administration with the recipes of the PHIL UX kit and
of the Shadcn kit: the role, the list, the page, the frame and its policy, every change posted with the stateless CSRF
token, refusals, conflicts and a foreign origin; then the services, a message sent through Messenger and Mailer, and the
commands.

The suite `tests/Integration` runs the same tests of the content store on MySQL 8.4 and PostgreSQL 17, the
byte-for-byte comparison of the sites and the keys included. It reads the servers from
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, which `vendor/bin/phil-dev services up` starts in Docker, and
skips without them.

From the monorepo root:

```sh
vendor/bin/phpunit packages/email-templates-bundle/tests
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/email-templates-bundle/tests/Integration
```

The bundle is held to 100 % covered mutation score by Infection.

## The fuzz target

`tests/Fuzz/render.php` renders templates nobody wrote with values nobody chose — a body, the JSON of the values and
the JSON of a schema, in HTML and in Markdown — and fails on anything but a result or an exception of the bundle, or an
output past the ceiling. Its named seeds are in `tests/Fuzz/corpus/render`:

```sh
vendor/bin/phil-dev fuzz render --budget=60
```

Each distinct source compiles a class of its own, so the memory of a fuzzing process grows with the inputs; an
application renders a bounded set of templates, each compiled once.

## The benchmark

`tests/Benchmark/RenderBench.php` measures, with the compiled templates warm as in a worker, the render of a Markdown
template and of an HTML one — sandbox, layout, inlined styles, plain text —, the resolution of a template and the plain
text alone: about 2 ms, 0.9 ms, 6 µs and 0.1 ms on a laptop.

```sh
vendor/bin/phil-dev bench packages/email-templates-bundle
```

## Testing an application

```yaml
# config/packages/test/phil_email_templates.yaml
phil_email_templates:
    store: memory
    sandbox: { cache: false }
```

`EmailRenderer::render()` returns the subject, the HTML and the text to assert on, nothing sent; Symfony's mailer
assertions see what `EmailComposer` composed and Mailer sent.
