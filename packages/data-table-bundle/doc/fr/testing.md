<!-- phil-dev source-sha256=1a99e17833266b7c21e591ef1e2f5ec6770a4a5fee6fd6328559a184da37aa31 -->
# Tests

```sh
vendor/bin/phpunit packages/data-table-bundle/tests
cd packages/data-table-bundle && node --import ./tests/js/register.mjs --test tests/js
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Les déclarations, l'état, les vues, les opérations, les lignes d'un export, les adaptateurs sur SQLite en mémoire, la garde, le contrôleur, le composant, les noms des recettes et le refus journalisé d'une préférence, sans noyau |
| `tests/Functional` | Un noyau avec une page de deux tableaux : l'état en simple GET, une cellule en bloc dessinée par le thème de cellules, le Live Component par sa route, les simples formulaires et le dialogue, l'avis d'une modification sortie de la page, l'export et ses refus, le CSRF et les *voters* |
| `tests/js` | Le contrôleur Stimulus sous l'exécuteur de tests de Node |
| `tests/Benchmark` | 100 lignes de 10 colonnes disposées en PHP, puis dessinées par le composant entier |
| `tests/Browser` | La page de l'application fonctionnelle dans un vrai Chrome, servie par le serveur intégré de PHP : un filtre saisi et soumis, une colonne masquée, un tri, le focus redonné après chacun, et le dialogue d'une action, modal, fermé par Échap, le focus revenu sur son bouton |

L'application fonctionnelle installe la recette Button du kit PHIL UX là où `ux:install` la met, dans le
sous-répertoire `ui` de ses composants, et vérifie l'export en CSV et en XLSX avec phil/data-table-export.

La suite au navigateur s'exécute à part, comme Infection, avec le `chromedriver` du système et le Stimulus de son propre
`package.json`, depuis la racine du monodépôt :

```sh
composer test:browser
```

Sans le Stimulus de `tests/Browser/node_modules`, comme lorsque le paquet est installé seul, la suite de navigateur est
ignorée plutôt qu'en échec.

Dans les tests propres à une application, `InMemoryDataSource` tient lieu de requête, et un `TableAccess` du test répond
aux attributs de *voter*.
