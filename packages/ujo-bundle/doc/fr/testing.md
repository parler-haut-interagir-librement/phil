<!-- phil-dev source-sha256=596bf5db4c89fe84cd90f41577f63bd713a27a92e40f97873c725fc87650e633 -->
# Tests

## Les tests du bundle

`tests/Unit` charge l'extension du bundle et vérifie chaque service et chaque option (`max_bytes` qui atteint le décodeur et l'assainisseur en ligne d'un conteneur compilé). Il teste aussi directement le
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
