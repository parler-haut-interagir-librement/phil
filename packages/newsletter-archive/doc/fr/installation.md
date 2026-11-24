<!-- phil-dev source-sha256=cf6b41d160e4d7b71dd1383a095f9ad7ca259f73a743950785ff1e4254bfecf2 -->
# Installation

```sh
composer require phil/newsletter-archive
```

Le bundle exige PHP 8.5 ou une version plus récente, avec `ext-ctype`, `ext-dom`, `ext-libxml`, `ext-mbstring` et
`ext-sodium`, ainsi que `phil/newsletter-bundle` 0.3 ou plus récent, installé et configuré : l'archive lit ses
campagnes, ses listes, ses gabarits, son moteur de rendu et son site courant.

## Le bundle

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_newsletter_archive.yaml` et
`config/routes/phil_newsletter_archive.yaml`. Sans elle, enregistrez le bundle :

```php
// config/bundles.php
return [
    // …
    Phil\NewsletterArchive\PhilNewsletterArchiveBundle::class => ['all' => true],
];
```

## Les routes

Les pages sont montées sous un préfixe au choix de l'application :

```yaml
# config/routes/phil_newsletter_archive.yaml
phil_newsletter_archive:
    resource: '@PhilNewsletterArchiveBundle/config/routes.php'
    prefix: /newsletter/archive
```

Une commande ou un worker qui écrit une adresse absolue — la commande affiche l'adresse d'un numéro et d'un lien —
lit l'hôte dans `framework.router.default_uri`.

## Les tables

L'archive garde ses numéros dans deux tables, `phil_newsletter_archive` et `phil_newsletter_archive_tag`, sur la
connexion de [`connection`](configuration.md). Avec l'ORM, `ArchiveSchemaListener` les ajoute au schéma qu'il génère, et
`doctrine:migrations:diff` écrit la migration qui les crée. Sans l'ORM, une migration appelle `ArchiveSchema::configure()`
sur le schéma qu'elle construit :

```php
$schema = ArchiveSchema::configure($schema, $connection->getDatabasePlatform());
```

Les instants sont des colonnes `datetime_utc_immutable`, écrites et lues en UTC. Sur MySQL et MariaDB, les clés, les
slugs, les étiquettes et les empreintes prennent la collation `utf8mb4_bin`, afin d'être comparés octet par octet.
