<!-- phil-dev source-sha256=df28b69e5be7fdf5d27031679227f554ded6118cf40c060ed5adce729570ca3f -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste la garde (une fois par throwable, les classes ignorées, une capture ratée journalisée une
fois et sans jamais boucler) ; l'analyseur des rapports des navigateurs sur les deux formats de pile, ses bornes et ce
qu'il refuse ; le récepteur et chacune de ses vérifications ; le résumé d'une requête, derrière un mandataire aussi, et l'écouteur des requêtes ; les
écouteurs des *workers* et des commandes, l'alerte en file d'attente et son *handler* ; le message de clavardage ; le
gestionnaire Monolog, ses niveaux et le tableau qu'un processeur de caviardage laisse à la place d'une exception ; le magasin DBAL sur SQLite, avec la course de deux premiers événements, le
plafond et la purge ; les pages d'administration rendues avec leurs vrais gabarits ; le limiteur ; la vérification de
santé, l'alerte notifiée et son sujet, le limiteur nommé ; la garde des limites de débit et la passe des alertes ; et
chaque définition que câble la configuration.

La suite `tests/Functional` démarre un noyau avec FrameworkBundle et DoctrineBundle sur SQLite et sert des requêtes
comme le ferait un *worker* : une page en échec groupée, assainie et notifiée, le jeton de son chemin et les adresses
des mandataires jamais gardés, une 404 laissée de côté, le rapport d'un navigateur gardé, les enregistrements du
caviardage des journaux de `phil/privacy-bundle` groupés par leur exception, la commande de purge, la vérification de
santé et le limiteur nommé.

Les tests du contrôleur Stimulus et de ses fonctions d'aide, dans `tests/js`, s'exécutent avec l'exécuteur de tests
propre à Node, Stimulus étant résolu en doublure par `register.mjs`. `tests/Functional/JavaScriptTest.php` les exécute
avec les autres suites, pour que `composer test` échoue quand l'un d'eux échoue ; sur une machine sans Node, il est
ignoré et le dit.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/error-tracking-bundle/tests
```

Ou les scripts seuls, depuis le répertoire du paquet :

```sh
node --import ./tests/js/register.mjs --test tests/js/
```

Le bundle est tenu par Infection à un score de mutation couverte de 100 %.

## La cible de fuzzing

`tests/Fuzz/browser.php` donne à l'analyseur des rapports des navigateurs ce que personne n'a écrit, à partir du
corpus de `tests/Fuzz/corpus/browser` : un corps est lu ou refusé avec `InvalidBrowserError`, rien d'autre, et chaque
rapport lu reste dans les bornes que promet l'analyseur. `composer fuzz browser` la lance pour son budget.

## Tester une application qui capture des erreurs

Lisez ce que le magasin a gardé par `ErrorGroupReader`, après avoir servi une requête dans un test de noyau :

```php
$client->request('GET', '/export');

$groups = self::getContainer()->get(ErrorGroupReader::class)->groups(null, 10);
self::assertSame('RuntimeException: The export failed', $groups[0]->title);
```

Dans un environnement de test qui ne devrait rien garder, mettez `sample_rate: 0.0`.
