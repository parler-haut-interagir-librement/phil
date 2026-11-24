<!-- phil-dev source-sha256=ed2eb91c96e118337d5b6c95e1d0977f46f14dd3fdd0398f868c3fb07e310505 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Chaque règle a un cas
conforme, un cas fautif et ses limites ; `SeoCheckerTest` vérifie une page entière, conforme et fautive ; l'adresse
canonique est vérifiée sur deux chemins menant à une page d'accueil, les directives d'indexation sur plusieurs canaux,
`hreflang` avec des traductions qui ne listent pas la page en retour. Les classes de `Bridge\` s'exécutent sur des pages
d'essai avec phil/structured-data, phil/social-cards et phil/link-checker, installés en développement ;
`LinkCheckerStatuses` s'exécute sur un `LinkChecker` dont la sonde répond d'après un scénario, `ScriptedLinkProbe`,
sans requête. Dans le monodépôt, Infection
maintient le paquet à 100 % des mutants couverts.

`tests/Support` contient les doublures des ports, `MemoryRenderedPages` et `MemoryLinkStatuses`, et les pages d'essai de
`Pages` ; les tests d'une application peuvent les copier.

## Fuzzing

La cible de fuzzing `seo-check-page`, `tests/Fuzz/page.php`, lit des pages que personne n'a écrites avec le lecteur de
balisage et les règles par défaut : quel que soit le contenu de la page, la vérification renvoie un rapport qui s'encode
en JSON, et ne lève jamais d'exception ni d'avertissement. Dans le monodépôt :

```sh
composer fuzz -- seo-check-page
```

Ses germes sont dans `tests/Fuzz/corpus/seo-check-page`.

## Banc d'essai

`tests/Benchmark/SeoCheckBench.php` vérifie un long article — quelque 600 liens, 100 images, 300 titres et les
traductions de dix langues, à son adresse et sur un deuxième chemin — avec chaque règle par défaut : le temps doit rester
proportionnel à la taille de la page.
