<!-- phil-dev source-sha256=9db775974900f97cdad97e2fa4a02912f6229b5da72be82eab9a188705a5ba30 -->
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

## Les requêtes de LiveComponent

`tests/Functional/LiveRequestsTest.php` envoie au composant les requêtes que le navigateur envoie, et le tient à ce que
LiveComponent 3.8.1 impose : sous le nom de sa classe plutôt que sous son nom, le composant répond 404 ; un 422 levé
avant son action, par un résolveur d'arguments ou un attribut de contrôleur, n'en produit aucun rendu ; et une
redirection répond 204 avec `X-Live-Redirect` et son `Location`, sans `X-Live-Url`, tandis qu'une réponse ordinaire
porte l'adresse que ses propriétés d'URL écrivent.
