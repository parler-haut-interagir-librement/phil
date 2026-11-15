<!-- phil-dev source-sha256=deb6269c5caa391f3b6b4b357a3c95486f8a984e706f61f3a06e03f5217d7189 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau ; les tests de
`FeedPublisher` écrivent dans un répertoire à eux, sous le répertoire temporaire du système, et le suppriment. Ils
vérifient la règle de chaque champ à sa limite et une unité au-delà, le RSS et le TSV exacts qui sont écrits, frais de
livraison compris, un fichier RSS entier relu par DOM, et qu'une source en échec, un renommage raté ou un flux trop
long laisse le fichier précédent et aucun fichier temporaire. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/feed-item.php` fabrique un article avec du texte que personne n'a écrit — son identifiant, son titre, sa
description, sa marque, sa couleur, son service de livraison et son étiquette personnalisée découpés dans l'entrée à
chaque `|` — et vérifie qu'il est refusé, ou écrit comme un seul article RSS qui s'analyse et rend son titre et son
service de livraison, et comme une seule ligne TSV avec une cellule par champ. Ses
germes sont dans `tests/Fuzz/corpus/feed-item`. Dans le monodépôt, `composer fuzz feed-item` l'exécute.

## Le banc d'essai

`tests/Benchmark/FeedPublisherBench.php` publie mille puis dix mille articles dans chaque format. Le temps croît avec
les articles, la mémoire non : environ 5 Mo pour les deux longueurs. Dans le monodépôt, `composer bench` l'exécute.
