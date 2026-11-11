<!-- phil-dev source-sha256=7ebe164360bea8746505e1d709b8fc45af2d870a2986e02ee7e7eefc3b0c621d -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Coordinates` | Les bornes, NaN et les infinis, le texte lu et écrit, l'égalité, le brouillage, les distances comparées à des valeurs connues, les antipodes |
| `Distance` | Les unités, la comparaison, les refus |
| `BoundingBox` | Des points dans toutes les directions, les bords, les pôles, l'antiméridien, un rayon plus grand que la Terre |
| Géocodage | `Place` et ses erreurs ; chaque adaptateur, dont Ressources naturelles Canada, Photon, OpenCage, HERE, Mapbox, Azure Maps et Adresses Québec, face aux réponses de `MockHttpClient` (lieux, chaque partie d'adresse, requêtes envoyées, agent utilisateur, rien de trouvé, clés refusées, erreurs de transport, réponses qui ne sont pas du JSON ou n'ont pas de coordonnées) ; la chaîne, le cache, ses clés et sa durée de vie |
| MaxMind | Des bases que les tests écrivent eux-mêmes (`MmdbWriter`) : fiches de 24, 28 et 32 bits, arbres IPv4 et IPv6, chaque type de la section de données dans chacune de ses tailles, pointeurs des quatre tailles, valeurs imbriquées sur 64 niveaux, chaque fichier endommagé refusé ; la fiche de ville transformée en lieu, dans une locale et en anglais |

Infection impose à la bibliothèque 100 % de mutants tués.

À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/geo/tests
```

Installée seule :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/geo/tests/Benchmark
```
