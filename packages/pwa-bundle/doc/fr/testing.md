<!-- phil-dev source-sha256=3cdab6eb9ee141ca589d55092a284fd0fe603ff2bc2c214376cd5086eb8a2962 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Le manifeste et ses règles, le lecteur, le JSON du manifeste, le script du worker et sa version, le contrôleur et les fonctions Twig, la configuration et sa passe. |
| `tests/Functional` | Un petit noyau avec FrameworkBundle, AssetMapper et TwigBundle : le manifeste avec une icône versionnée par AssetMapper, `/sw.js` et le worker qu'il importe, servi par AssetMapper, les en-têtes d'un worker servi sous sa portée, la page hors ligne, la mise en page. |
| `tests/js` | Sous l'exécuteur de tests de Node, avec `@hotwired/stimulus` résolu vers une doublure par `register.mjs` : le worker dans un contexte à part, avec un stockage de cache en mémoire et un réseau auquel le test répond — installation, activation, chaque stratégie, hors ligne, ce qui n'est jamais conservé —, et les deux contrôleurs avec un navigateur fait d'objets simples. |

Rien n'a besoin d'un réseau ni d'un navigateur. Dans le monodépôt, Infection maintient le PHP à 100 % des mutants
couverts.

## Fuzzing

`tests/Fuzz/manifest.php` lit un manifeste à partir d'un JSON que personne n'a écrit : il le lit avec son début dans sa
portée et ses chemins locaux, ou le refuse avec `InvalidManifest`. À la racine du monodépôt :

```sh
composer fuzz -- manifest --budget=60
```

## L'essayer dans un navigateur

Les navigateurs n'enregistrent un worker que sur HTTPS ou `localhost`. Dans les outils de développement, le panneau
Application montre le manifeste, le worker, ses caches, et un interrupteur Hors ligne pour essayer la page hors ligne.
