<!-- phil-dev source-sha256=64ce61e4e715b8544c18dadffee76730232bec4587ee6331c8de1b9f62da038c -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle produit, l'association du contrôleur et de la feuille de
style, et les deux filtres : texte brut échappé, HTML digne de confiance seulement sous forme de `Markup`, assaini ou
échappé sinon, documents lus et rendus par les services UJO de l'application. `tests/Functional` démarre une petite
application — FrameworkBundle avec AssetMapper et un assainisseur HTML, TwigBundle, `phil/ujo-bundle` et ce bundle —,
produit le rendu d'un gabarit avec les deux filtres, et trouve le contrôleur et la feuille de style par AssetMapper.
`tests/js` vérifie le contrôleur et les décisions qu'il prend sans le DOM.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/text-diff-bundle/tests
(cd packages/text-diff-bundle && node --import ./tests/js/register.mjs --test tests/js)
```

Infection maintient le bundle à un score de 100 % de mutants couverts, comme la bibliothèque.

## Tester une application

La sortie des filtres est déterministe : un test fonctionnel vérifie le HTML rendu, ou compte les `ins` et les `del`
d'une page avec un *crawler*. Un test du comportement du contrôleur s'exécute dans un navigateur — Panther ou
Playwright — et vérifie `data-text-diff-mode`, `aria-pressed`, le texte du compteur et l'élément marqué
`text-diff-current`.
