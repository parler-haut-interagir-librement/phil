<!-- phil-dev source-sha256=3e6d481a5663b612610a12ec7ad29e9588bb1d86dd1345055ab5614201618574 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Directives et valeurs | Chaque sorte de valeur, la grammaire de chaque directive, les bornes, les mots-clés sans guillemets, les sauts de ligne |
| Politiques | L'ordre, `'none'`, les drapeaux, l'ajout à partir de `default-src`, le remplacement, le retrait, l'héritage, les jetons d'une requête |
| Politiques nommées | `extends` et ses cycles, les empreintes du code en ligne, Trusted Types, les options par politique |
| Surcharges | Les attributs, les valeurs par défaut de route et leurs refus, l'ordre de replace, add et remove, pour la politique et pour chaque autre protection |
| En-têtes voisins | Chaque valeur et son refus, l'option de cadre d'un chemin, un en-tête gardé ou forcé, HSTS sur HTTPS seulement, le point d'entrée des rapports |
| HTTPS, cookies, redirections | La redirection et ses exceptions, les signatures et leur rotation, les cibles résolues comme un navigateur les résout |
| Nonces et état | Un nonce par sorte et par requête, le nonce partagé, les empreintes, la requête principale |
| En-têtes | Appliquée et report-only, directives et point d'entrée des rapports, `'strict-dynamic'`, directives d'élément, surcharges |
| L'écouteur | Les surcharges de route et de contrôleur, une réponse avec sa propre politique, une réponse avec un nonce rendue privée, les sous-requêtes, les chemins exclus, l'adresse des rapports |
| Rapports | Les deux formats, les rapports des autres politiques, chaque champ et sa borne, la confidentialité des adresses, les corps mal formés, le nombre de rapports |
| Le récepteur et le point d'entrée | Chaque reçu, la clé de la limitation de débit, le dédoublonnage dans un corps et dans la fenêtre, le magasin, l'événement |
| Les adaptateurs | Le rate limiter et les stockages que surveille la garde de phil/rate-limit, le limiteur de phil/rate-limit-bundle, le dédoublonneur PSR-6, l'adresse tronquée, le magasin DBAL et sa course, la commande de purge |
| Twig | `csp_nonce()`, `csp_meta()`, `importmap()` avec le nonce |
| Contenu d'usager | Chaque en-tête, les types affichés et les types actifs, une réponse sans type, le nom de fichier gardé ou donné et rendu sûr, la configuration et ses refus, la valeur par défaut de route, l'attribut et `apply()` dans un noyau |
| Le bundle | Chaque option et chaque refus de la configuration ; un noyau avec AssetMapper, Twig, SecurityBundle et SQLite qui sert des pages et des rapports |

Infection tient le bundle à 100 % des mutants tués.

```sh
vendor/bin/phpunit packages/browser-security-bundle/tests
```

## Fuzzing

`tests/Fuzz/report.php` lit des corps que personne n'a écrits sous chaque type de média : un corps est lu ou refusé
avec `InvalidReport`, rien d'autre, et chaque rapport lu reste dans ses bornes : ni chaîne de requête, ni fragment, ni
identifiants, ni caractère de contrôle dans une adresse, une directive valide, un échantillon de 40 caractères au plus.

```sh
composer fuzz -- report
```

## Banc d'essai

`tests/Benchmark/HeaderBuilderBench.php` construit les en-têtes d'une réponse, avec la politique par défaut appliquée et
une plus stricte en report-only. Sur un portable récent :

| Sujet | Temps |
|---|---|
| Sans nonce | environ 4 µs |
| Avec deux nonces et une empreinte | environ 9 µs |
| Avec une surcharge de la route | environ 18 µs |
| Un nouveau nonce | environ 1 µs |

```sh
composer bench -- packages/browser-security-bundle/tests/Benchmark
```
