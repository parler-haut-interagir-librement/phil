<!-- phil-dev source-sha256=1e2dbd610b0c861390aee12c2a2623e353c2c48e83ed216baa8c525cbf610c17 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Les valeurs | Chaque identifiant, texte, quantité, lieu, contenant, article, mouvement, ligne de chargement et liste de chargement, et chaque refus avec son message |
| `Stockroom` | Contenants imbriqués, déplacés, bornés en profondeur, boucles repérées ; articles et leurs codes ; chaque mouvement, sa ligne de journal et ses soldes ; le lieu d'origine pris en premier ; unités suivies ; états ; listages et leurs bornes |
| `Deployments` | Plans, prélèvement, déploiements tout ou rien et partiels, terminés plus tard, les contenants que les lignes nomment, la vérification au retour, radiations, articles sans lieu d'origine, identifiants faits de chiffres seulement |
| `LoadListReader` | Chaque forme de ligne, l'aller-retour, chaque refus avec son numéro de ligne, les bornes du texte |
| Les magasins en mémoire | L'ordre, les limites et la réinitialisation de chaque port |

```sh
vendor/bin/phpunit packages/stockroom/tests
```

La suite unitaire s'exécute sans base de données ni réseau. Dans le monodépôt, Infection maintient le paquet à 100 % des
mutants couverts.

## Fuzzing

Le lecteur de listes de chargement est une cible de fuzzing, `load-list`, amorcée depuis
`tests/Fuzz/corpus/load-list` : quel que soit le texte reçu, il retourne des lignes qui s'écrivent en un texte relu
comme les mêmes lignes, ou lève `InvalidStock`, et rien d'autre.

```sh
composer fuzz -- load-list
```
