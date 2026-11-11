<!-- phil-dev source-sha256=57b3c3f97540df93fc81451d4877ba8b6aa4ad5a3fccdc096e326ce186df0364 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle fournit, la déclaration de la feuille de style, les
langages et les alias ajoutés par la passe de compilation, la décoration du moteur de rendu UJO et le filtre.
`tests/Functional` démarre une petite application (FrameworkBundle avec AssetMapper, TwigBundle, `phil/ujo-bundle`, un
langage qui lui est propre et ce bundle), produit le rendu d'un gabarit avec le filtre et d'un document UJO, et trouve
la feuille de style par AssetMapper.

À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/highlight-bundle/tests
```

Infection impose au bundle, comme à la bibliothèque, un score de mutation de 100 % sur le code couvert.

## Tester une application

Un test fonctionnel vérifie le HTML produit. Les `span` sont ceux de `phil/highlight`, stables d'une exécution à
l'autre ; un test qui vérifie qu'un bloc est coloré, plutôt que la façon dont il l'est, cherche `class="hl-` dans son
`code`. Pour comparer le texte seul, vérifiez le code une fois ses `span` retirés et ses caractères déséchappés.
