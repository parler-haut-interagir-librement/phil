<!-- phil-dev source-sha256=fbb879c419b4c8d5e8976f27271caf40dfa612f3e0a38bd13de219be1596f649 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/content-bundle` | ^0.1@dev | Les fiches sont des contenus : versions, flux de travail, routes |
| `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` | ^0.1@dev | La fiche de contact, l'adresse et la position d'une fiche, leurs colonnes et leurs types de formulaire |
| `phil/ddd-bundle` | ^0.1@dev | L'identifiant d'une fiche et son type Doctrine |
| `phil/sitemap` | ^0.1@dev | Le fournisseur du plan du site, recueilli lorsque `phil/sitemap-bundle` est installé |
| `phil/indexing-consent` | ^0.1@dev | Qui trouve une fiche, comme son auteur en décide |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | Les deux tables ; la plateforme de la connexion, que lit `BinaryKeysListener` |
| `symfony/form`, `symfony/routing`, `symfony/http-kernel` | ^8.2 | Les formulaires, les routes et le contrôleur |
| `symfony/security-core` | ^8.2 | La personne connectée, et le *voter* |
| `twig/twig` | ^3.21 | Les pages |

```sh
composer require phil/directory-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
    Phil\ContactBundle\PhilContactBundle::class => ['all' => true],
    Phil\AddressBundle\PhilAddressBundle::class => ['all' => true],
    Phil\GeoBundle\PhilGeoBundle::class => ['all' => true],
    Phil\DirectoryBundle\PhilDirectoryBundle::class => ['all' => true],
];
```

## Ce que le bundle ajoute au préalable

Lorsque l'extension est enregistrée, le bundle ajoute au préalable :

- à `doctrine` : le type `phil_directory_listing_id` et l'association par attributs `PhilDirectory` de
  `src/Domain/Model` ;
- à `phil_content` : `Listing` comme racine de contenu, sous la clé de ressource `listing`.

L'application crée les tables avec ses propres migrations. La table des fiches garde qui trouve une fiche dans deux
colonnes, `indexing` (`listed` par défaut) et `site_listed` (vrai par défaut), pour qu'une migration les ajoute aux
lignes existantes sans changer ce qu'elles montrent.

### Collation des clés

**Le genre, l'auteur et celui qui a écrit une version se comparent octet par octet**, comme PHP les compare. Un
identifiant d'utilisateur peut mêler les casses : une requête sur `author` pour `Ann` — la liste des fiches d'un
utilisateur dans une application — ne renvoie jamais celles de `ann`, et effacer `Ann` ne vide jamais `edited_by` sur
les versions qu'`ann` a écrites. Le bundle écrit les genres en ASCII minuscule ; une ligne écrite en dehors de lui dans
le genre `Podcast` ou `pódcast` n'est jamais listée sous `podcast`. `BinaryKeysListener` donne à ces colonnes la
collation binaire lorsque l'ORM charge les métadonnées de `Listing`, et de toute classe qui implémente
`EditedByCapable`, sur MySQL ou MariaDB ; le mappage lui-même reste le même sur toutes les plateformes. `edited_by` est
mappé par l'écouteur de mappage de content-bundle sur le même événement : le bundle enregistre `BinaryKeysListener`
après lui, avec une priorité plus basse.
Les clés des lignes d'une fiche — langue et étape — sont celles de content-bundle, qui leur donne la même collation.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_directory_listing` | `genre`, `author` |
| `phil_directory_listing_dimension` | `edited_by` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène `genre`, `author` et `edited_by` à `utf8mb4_bin` ; exécutez-la
avec `doctrine:migrations:migrate`. À la main, selon la plateforme :

```sql
-- MySQL and MariaDB
ALTER TABLE phil_directory_listing
    MODIFY genre VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
    MODIFY author VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
ALTER TABLE phil_directory_listing_dimension
    MODIFY edited_by VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
-- PostgreSQL: nothing to run, the default collation is deterministic.
-- SQLite: nothing to run, the columns compare bytes.
```

Le changement ne fait que distinguer davantage les valeurs ; aucune de ces colonnes ne fait partie d'une clé unique. Il
sépare ce que la collation insensible à la casse réunissait : un genre ou un auteur qu'une recherche dans une autre
casse trouvait cesse de correspondre dès la migration. Avant de migrer, listez les auteurs et les rédacteurs de versions
écrits dans plus d'une casse :

```sql
SELECT author, COUNT(*) AS row_count FROM phil_directory_listing
GROUP BY author HAVING COUNT(DISTINCT CAST(author AS BINARY)) > 1;
SELECT edited_by, COUNT(*) AS row_count FROM phil_directory_listing_dimension
GROUP BY edited_by HAVING COUNT(DISTINCT CAST(edited_by AS BINARY)) > 1;
```

Chaque ligne est un utilisateur sous plusieurs orthographes. Gardez-les séparées quand ce sont deux utilisateurs, ou
ramenez les lignes à l'orthographe de l'identifiant d'utilisateur par un `UPDATE` avant la migration.

## Sécurité

Le bundle lit les services `security.token_storage` et `security.authorization_checker`, que fournit SecurityBundle.
L'utilisateur est l'identifiant du jeton — `getUserIdentifier()` —, et un administrateur est un utilisateur à qui le
rôle `admin_role` est accordé, par la hiérarchie des rôles lorsqu'il y en a une. Une personne non connectée lit les
fiches publiées, et rien d'autre.
