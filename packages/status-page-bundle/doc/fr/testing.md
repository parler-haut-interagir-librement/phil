<!-- phil-dev source-sha256=b4bf0bd47886ede6c278f7faf8e9d357008ac565defbd4de5e4151a7f6c4ce23 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Le domaine, le lecteur d'enregistrements, le constructeur, le JSON et le flux, la publication, les sources avec une doublure du modèle de lecture de `phil/monitoring-bundle`, les magasins et les éditeurs sur un répertoire temporaire et un Flysystem en mémoire, la configuration et ses passes. |
| `tests/Functional` | Un petit noyau avec FrameworkBundle, Messenger, le Scheduler, TwigBundle et Twig Component, les recettes du kit copiées là où `ux:install` les met, une horloge figée dans le temps : la page, le JSON et le flux par HTTP, la commande, le message et l'horaire qui écrivent un instantané dans un répertoire. |

Rien n'a besoin d'un réseau ni d'un serveur. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.

## Fuzzing

`tests/Fuzz/status-records.php` lit des incidents et des maintenances à partir d'un JSON que personne n'a écrit : les
enregistrements se lisent, ou sont refusés avec `InvalidStatusRecord`, et rien d'autre n'est lancé. À la racine du
monodépôt :

```sh
composer fuzz -- status-records --budget=60
```

## Tester une application

`StatusPageBuilder` prend une horloge PSR-20 : donnez au noyau de test une `MockClock` comme `clock` et la page est la
même à chaque exécution. Une cible `directory` sous le répertoire temporaire du test rend `phil:status-page:push`
observable sans réseau.
