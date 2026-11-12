<!-- phil-dev source-sha256=056012cd9f86cffec9bc485fdc3a2ee2618fca6789a3af9de7557797f0c316f1 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `LoupeIndex` | Chaque mot, chaque filtre, les listes, la liste de valeurs d'une requête, les facettes comptées sans leur propre filtre, la recherche dans le texte seulement, les pages, les nouveaux champs et la reconstruction, un nouveau champ sur 150 documents sur disque copiés une page à la fois dans un nouveau répertoire ou laissés tels quels quand la copie échoue, le fichier des champs relu seulement quand il a changé, une écriture qui attend le changement de répertoire d'un autre processus, les champs et les reconstructions d'un autre processus, les champs ou le répertoire gardés de travers, le verrou tenu pendant la bascule, un seul Loupe ouvert tant que rien ne change, le répertoire d'index créé ou signalé, la mémoire |

Les tests écrivent leurs index dans un répertoire temporaire à eux et le suppriment ; un avertissement PHP non réduit
au silence les fait échouer.

```sh
vendor/bin/phpunit packages/search-loupe/tests
```

## Bancs d'essai

Les sujets phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/search-loupe/tests/Benchmark
```
