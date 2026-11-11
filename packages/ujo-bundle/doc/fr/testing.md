<!-- phil-dev source-sha256=e75dde0bed2322669e7fb76f8775cec90546e6ae00b5c6de2ec31bc2c4853de7 -->
# Tests

## Les tests du bundle

`tests/Unit` charge l'extension du bundle et vérifie chaque service et chaque option. Il teste aussi directement le
transformateur, le validateur (sur le `ConstraintValidatorTestCase` de Symfony) et l'extension Twig.
`tests/Functional` démarre un petit noyau avec FrameworkBundle et TwigBundle, produit le rendu d'un gabarit avec les
filtres et les fonctions, envoie le champ et valide en français.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/ujo-bundle/tests
```

## Tester un formulaire qui contient un document

Envoyez le JSON que l'éditeur écrirait, et vérifiez la forme canonique :

```php
$form = $this->getContainer()->get('form.factory')->create(ArticleType::class);
$form->submit(['body' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>Hi</strong>"}}]}']);

self::assertTrue($form->isValid());
self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>Hi</b>"}}]}', $form->getData()->body);
```
