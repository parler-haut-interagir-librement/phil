<!-- phil-dev source-sha256=f936ead5dec21a5027a482b6a7248e46281102220f21419f57da22284e4e050e -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau : le domaine, les règles, l'exécuteur et les en-têtes sur des
limiteurs en mémoire, les adaptateurs sur le vrai `symfony/rate-limiter`, la consommation de l'attribut de Symfony, les clés avec de vrais
jetons et phil/ip-anonymizer, l'extension de formulaire sur le vrai composant de formulaire, le répondeur sur Twig et le
traducteur, et la configuration du conteneur et sa passe de compilation sur un `ContainerBuilder`. La suite
fonctionnelle, sous `tests/Functional`, démarre une application avec FrameworkBundle et TwigBundle : un contrôleur
limité par les attributs de Symfony qui répond avec sa page en français, un attribut sans clé compté par la stratégie du
limiteur, une clé explicite gardée hachée et la limite consommée remise à l'action, la valeur par défaut de route comptée
avant les attributs, une API limitée par sa valeur par défaut de route qui répond par un problème en JSON, un limiteur de
la configuration du framework qui répond avec la page, un formulaire limité par son nom, chaque refus réparti comme
l'événement du bundle et celui de Symfony.

Dans le monodépôt, les tests s'exécutent sur PHP 8.5 et 8.6, et Infection impose au paquet 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/RateLimitListenerBench.php` mesure ce que le bundle ajoute à une requête (aucune règle, une règle et
trois dans une valeur par défaut de route, et une consommation de l'attribut de Symfony, chacune sur une fenêtre
glissante dans un stockage en mémoire, et les en-têtes sur la réponse), les entrées-sorties propres au stockage mises à
part.

```sh
composer bench -- --filter=RateLimitListenerBench
```
