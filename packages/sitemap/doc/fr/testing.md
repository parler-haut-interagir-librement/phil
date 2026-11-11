<!-- phil-dev source-sha256=998ba941ce591320e08677dd935a9734e0d693e7b7b18124d1f1bc6864b25d24 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Url`, `Alternate`, `Image`, `ChangeFrequency` | Chaque champ et ses bornes, les adresses refusées, les graphies de `hreflang`, les images d'une page |
| `Sitemap` | Les pages par section, une seule section, les fichiers et les parties numérotées, les bornes d'un fichier, les sections refusées |
| `XmlWriter` | Le XML exact d'un fichier et d'un index, l'échappement, un index sans dates, une entrée d'index qui n'est pas une adresse |

```sh
vendor/bin/phpunit packages/sitemap/tests
```

## Bancs d'essai

`XmlWriterBench` écrit des fichiers de 1 000, 10 000 et 50 000 pages, simples ou avec variantes et images. Depuis la
racine du monodépôt :

```sh
composer bench -- packages/sitemap/tests/Benchmark
```
