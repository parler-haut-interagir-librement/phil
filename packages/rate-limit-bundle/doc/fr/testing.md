<!-- phil-dev source-sha256=e0031b82c3ad8fecedec0fdb149c6ec2c1fbf7f116b37e91467c8bf4bf104fa4 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau : le domaine, les règles, l'exécuteur et les en-têtes sur des
limiteurs en mémoire, l'adaptateur sur le vrai `symfony/rate-limiter`, les clés avec de vrais jetons et
phil/ip-anonymizer, l'extension de formulaire sur le vrai composant de formulaire, le répondeur sur Twig et le
traducteur, et la configuration du conteneur et sa passe de compilation sur un `ContainerBuilder`. La suite
fonctionnelle, sous `tests/Functional`, démarre une application avec FrameworkBundle et TwigBundle : un contrôleur
limité par ses attributs qui répond avec sa page en français, une API limitée par sa valeur par défaut de route qui
répond par un problème en JSON, un formulaire limité par son nom, chaque refus réparti.

Dans le monodépôt, les tests s'exécutent sur PHP 8.5 et 8.6, et Infection impose au paquet 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/RateLimitListenerBench.php` mesure ce que l'écouteur ajoute à une requête (aucune règle, une règle et
trois, chacune sur une fenêtre glissante dans un stockage en mémoire, et les en-têtes sur la réponse), les
entrées-sorties propres au stockage mises à part.

```sh
composer bench -- --filter=RateLimitListenerBench
```
