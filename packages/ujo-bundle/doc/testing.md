# Testing

## The bundle's tests

`tests/Unit` loads the bundle's extension and checks every service and option, and tests the transformer, the
validator — on Symfony's `ConstraintValidatorTestCase` — and the Twig extension directly. `tests/Functional` boots a
small kernel with FrameworkBundle and TwigBundle, renders a template with the filters and functions, submits the
field and validates in French.

From the monorepo root:

```sh
vendor/bin/phpunit packages/ujo-bundle/tests
```

## Testing a form that holds a document

Submit the JSON the editor would write, and assert on the canonical form:

```php
$form = $this->getContainer()->get('form.factory')->create(ArticleType::class);
$form->submit(['body' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>Hi</strong>"}}]}']);

self::assertTrue($form->isValid());
self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>Hi</b>"}}]}', $form->getData()->body);
```
