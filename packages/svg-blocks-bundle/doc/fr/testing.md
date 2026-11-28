<!-- phil-dev source-sha256=9415c6dc61efdf97df014fc7bb1a6c61638d7df490788c87a580ffb6c32e78b1 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

`tests/Unit` vérifie la configuration et ses services, la passe de compilation, l'extension de formulaire, les
réponses et les vérifications de l'aperçu, et la passe en arrière-plan. `tests/Functional` démarre une petite
application (formulaires, routeur, Messenger, AssetMapper, un client HTTP qui tient lieu de service Mermaid) : elle
soumet des documents et des formules (macros, couleurs, numéros et chimie, entre autres), en produit le rendu, affiche
l'aperçu de blocs avec le jeton CSRF sans état, sert le contrôleur par AssetMapper, et lance la passe en arrière-plan,
le style d'un diagramme étant conservé et réécrit. `tests/js` exécute les modules purs du contrôleur, et `lib.mjs` du
service Mermaid (son limiteur, son délai, sa limite de corps, la configuration de Mermaid et de Chromium), sous
l'exécuteur de tests de Node. Dans le monodépôt, Infection maintient le PHP à 100 % de mutants couverts.

Le serveur du service Mermaid est vérifié avec Mermaid par le test d'intégration de `phil/svg-blocks`, à
`PHIL_MERMAID_URL`, une fois l'image construite et lancée.

## Tester une application

Ne configurez aucun `mermaid.url` dans l'environnement de test, ou faites pointer le `http_client` vers une fabrique de
réponses *mock*. Donnez à `mermaid.documents` une implémentation en mémoire. Exécutez le transport de Messenger de
façon synchrone.
