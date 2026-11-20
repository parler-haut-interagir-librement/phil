<!-- phil-dev source-sha256=aaa1d5691a1b9d6135f7b9ef42d7efe205d0b3913bd4e65c4d58c1a1104c2292 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Listing` | Le genre et l'auteur fixés une seule fois, les rédacteurs et leurs bornes, qui peut modifier, un utilisateur effacé oublié |
| Les cinq pipelines | L'association, la fusion et la normalisation du contact, de l'adresse, de la position, des fiches liées et du rédacteur, une demi-position |
| Les relations | `Relations` par clé et par genre, une relation nommée comme un genre, les titres de `RelatedListings`, la forme de `related` refusée lorsqu'elle n'est pas faite de listes par relation |
| `BinaryKeysListener` | `genre`, `author` et `edited_by` en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB avec l'écouteur de mappage de content-bundle, la collation par défaut sur SQLite, où des valeurs qui ne diffèrent que par la casse ou l'accent sont quatre lignes, les options qu'un champ a déjà conservées, `editedBy` laissé tel quel lorsqu'il n'est pas mappé, une autre entité laissée telle quelle, et l'écouteur exécuté après l'écouteur de mappage dans le noyau de test |
| La configuration et les services | L'arbre, les valeurs par défaut, les services, ce qui est ajouté au préalable et quand |
| `DirectoryRoutes` | Les routes de chaque genre, leurs noms, leurs méthodes et leurs contraintes |
| `ListingVoter`, `Actors` | Chaque attribut pour l'auteur, un rédacteur, un inconnu, un administrateur et une personne non connectée |
| Un répertoire dans une application | La rédaction, la modification en tant que rédacteur, le brouillon conservé, la publication, l'historique, un ancien *slug* redirigé, le plan du site, un *slug* déjà pris, les pages d'un genre, une fiche sans *slug* laissée de côté, les fiches liées des deux côtés et leurs vérifications, les fiches qui en nomment une recherchées parmi les lignes actives, un utilisateur effacé, une fiche gardée hors des moteurs de recherche puis non listée : le plan du site, la liste de son genre, les fiches liées, ses directives aux robots et qui peut décider |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur un fichier SQLite, TwigBundle,
content-bundle et le bundle, avec les services de sécurité construits à la main : `DirectoryBundleTest` se connecte en
définissant un jeton et parcourt chaque page avec le BrowserKit.

```sh
vendor/bin/phpunit packages/directory-bundle/tests
```

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : les fiches des
auteurs `ann`, `Ann`, `ANN` et `ánn`, dans des genres de mêmes noms, et les versions qu'ils ont écrites, sont chacune
trouvées par leur seul auteur, genre et rédacteur, et sur MySQL les trois colonnes sont en `utf8mb4_bin` et les tables
de la version précédente l'atteignent par le diff du schéma. Le test de chaque serveur lit son URL dans
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/directory-bundle/tests/Integration
```

## Bancs d'essai

`DirectoryBench` démarre l'application fonctionnelle sur SQLite en mémoire et lit les fiches liées et les fiches
publiées d'un genre, avec 10, 100 et 500 GN. Depuis la racine du monodépôt :

```sh
composer bench -- packages/directory-bundle/tests/Benchmark
```
