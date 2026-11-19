<!-- phil-dev source-sha256=326aa552a66cb0bf803e038a042c84953537c4d000fcda0353677e1095fc5bbb -->
# Installation

```sh
composer require phil/library-bundle
```

Le bundle exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.7 et DBAL
4.5 ou une version plus récente, Twig et Symfony UX Twig Components. Avec Symfony Flex, la recette enregistre le bundle
et écrit sa configuration et ses routes ; sinon :

```php
// config/bundles.php
Phil\LibraryBundle\PhilLibraryBundle::class => ['all' => true],
```

## Le schéma

Avec DoctrineBundle, le bundle associe lui-même ses entités ; les migrations de l'application créent trois tables,
`phil_library_title`, `phil_library_copy` et `phil_library_loan` :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Les moments sont conservés dans des colonnes `datetime_utc_immutable`. Le code à barres d'un exemplaire est unique ;
supprimer un titre supprime ses exemplaires et leurs prêts.

## Les routes

```yaml
# config/routes/phil_library.yaml
phil_library:
    resource: '@PhilLibraryBundle/config/routes.php'
    prefix: /library
```

Les pages du personnel exigent les rôles de `roles` — `ROLE_LIBRARIAN` par défaut —, de sorte que la sécurité de
l'application donne ce rôle à ses bibliothécaires. Sans sécurité, ces pages sont refusées à tout le monde. Voir
[Pages](pages.md).

## Les recettes du kit

Les gabarits dessinent leurs éléments avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit :

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## Paquets facultatifs

| Paquet | Ce qu'il ajoute |
|---|---|
| `symfony/messenger` | Les rappels envoyés sous forme de messages que l'application gère |
| `symfony/console` | `phil:library:send-reminders` et `phil:library:forget-history` |
| `phil/privacy-bundle` | Les prêts d'un membre dans l'export et l'effacement de ses données |
| `phil/stockroom` | Les mouvements de stock des exemplaires qui sont des articles d'une réserve |
| `phil/stockroom-bundle` | La réserve dans la base de données, chaque mouvement verrouillé sur son article et gardé dans la transaction du prêt quand les deux partagent la connexion |
| `easycorp/easyadmin-bundle` | Les liens de la bibliothèque dans une interface d'administration |
| `phil/search-bundle` | Les titres dans la recherche du site, indexés par l'application |
