<!-- phil-dev source-sha256=e817f5300b4bcf0b5d12fb713999cd4675dd25fec251ee15da1556468fc227df -->
# Tests

## Les tests du paquet

`tests/Unit` couvre le lecteur sur un message imbriqué de chaque multipartie courante et sur un rapport de livraison,
des digests, des parties sans lignes d'en-tête ni corps, des fins de ligne des deux sortes, des types invalides, des
frontières inutilisables, des délimiteurs qui ne sont pas seuls sur leur ligne, des lignes de fermeture manquantes, des
conteneurs encodés, chaque limite à sa borne et des messages de forme hostile ; les champs d'en-tête et leur
dépliage, les mots encodés, les paramètres avec sections et jeux de caractères, les listes d'adresses ; des parties
construites à partir de valeurs, des corps décodés, convertis et lus en flux, les dispositions, les noms de fichier et
les pièces jointes ; et les décodeurs aux frontières de leurs morceaux.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/mime-reader/tests
```

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/mime-reader.php` soumet le lecteur au fuzzing avec les germes de `tests/Fuzz/corpus/mime-reader` : quoi
qu'il lise, il retourne une partie et ne lance jamais d'exception ni d'avertissement ; les parties qu'il lit restent
dans les limites, tout texte qu'il donne est de l'UTF-8 valide, et aucun nom de fichier qu'il propose ne contient de
répertoire.

```sh
composer fuzz -- mime-reader
```

## Les bancs d'essai

`tests/Benchmark/MimeReaderBench.php` lit un rapport de livraison, un message imbriqué, un message avec une pièce
jointe de 4 Mo écrite vers un flux, et des messages de forme hostile : un mégaoctet de texte, cinquante mille parties,
mille niveaux d'imbrication et deux cents frontières jamais fermées. Chacun coûte un temps qui croît avec les octets,
borné par les limites.

```sh
vendor/bin/phil-dev bench -- --filter=MimeReaderBench packages/mime-reader/tests/Benchmark
```
