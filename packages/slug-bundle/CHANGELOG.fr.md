<!-- phil-dev source-sha256=fff3a312f2e012f1ba206c79616e8ba018066223b93b9cd6b7a5f2c7772257ba -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/slug-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.1-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.2.0-alpha - 2026-11-18

La portée, l'ancien slug et le sujet de l'historique se comparent octet par octet sur MySQL et MariaDB. Voyez [Mise à
niveau depuis 0.1](doc/fr/history.md#mise-à-niveau-depuis-01).

### Modifié

- `SlugHistoryTable::configure()` prend la plateforme de la connexion comme second argument, et l'écouteur du schéma la
  lui donne : sur MySQL et MariaDB, `scope`, `former_slug` et `subject` de `phil_slug_history` prennent le jeu de
  caractères `utf8mb4` et la collation `utf8mb4_bin`. PostgreSQL et SQLite gardent leur valeur par défaut, qui compare
  déjà les octets.

### Corrigé

- Sous la collation par défaut insensible à la casse de MySQL et de MariaDB, consigner un changement du sujet `Post-7`
  réécrivait le slug actuel de `post-7` et `forget()` retirait son historique, et un ancien slug de la portée `Blog`
  redirigeait vers le sujet de `blog`.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilSlugBundle` et la configuration `phil_slug` : des générateurs de slugs nommés avec `locale`, `max_length` et
  `leading_words`, et celui par défaut, `default`. Un mot initial qui n'est pas un seul mot de slug est refusé à la
  construction du conteneur.
- Un service par générateur de slugs, `phil_slug.slugger.<name>`, à décorer ; `Slugger` autoconnecté à celui par défaut,
  et `Slugger $<name>Slugger` à chacun.
- `Sluggers`, chaque générateur de slugs par nom, comme service public.
- `SlugExtension` : le filtre Twig `phil_slug`.
- L'historique des anciens slugs, facultatif : `phil_slug.history` avec sa `connection` et son `clock` ; le contrat
  `SlugHistory` (`record()`, `currentSlug()`, `formerSlugs()`, `forget()`), qui ne garde aucune chaîne et retire un slug
  qui redevient actuel ; `SlugHistoryKeys` et `InvalidSlugHistory` pour la portée et le sujet ; `DoctrineSlugHistory`
  sur la table `phil_slug_history`, `SlugHistoryTable` et `SlugHistorySchemaListener`, qui ajoute la table au schéma que
  génère l'ORM. Testé sur SQLite, MySQL 8 et PostgreSQL.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
