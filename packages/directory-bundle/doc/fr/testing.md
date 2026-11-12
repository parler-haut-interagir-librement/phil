<!-- phil-dev source-sha256=5e80b788251de34f92d960a7799cdf095189864a808e4c1a165747103f5cf367 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Listing` | Le genre et l'auteur fixés une seule fois, les rédacteurs et leurs bornes, qui peut modifier, un utilisateur effacé oublié |
| Les cinq pipelines | L'association, la fusion et la normalisation du contact, de l'adresse, de la position, des fiches liées et du rédacteur, une demi-position |
| Les relations | `Relations` par clé et par genre, une relation nommée comme un genre, les titres de `RelatedListings`, la forme de `related` refusée lorsqu'elle n'est pas faite de listes par relation |
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

## Bancs d'essai

`DirectoryBench` démarre l'application fonctionnelle sur SQLite en mémoire et lit les fiches liées et les fiches
publiées d'un genre, avec 10, 100 et 500 GN. Depuis la racine du monodépôt :

```sh
composer bench -- packages/directory-bundle/tests/Benchmark
```
