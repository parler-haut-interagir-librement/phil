<!-- phil-dev source-sha256=fd2138de86dbce910a8118a3520d55b5a0b8adbcab9dc9d81bf69b666f4791f4 -->
# Installation

```sh
composer require phil/access-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php` :

```php
Phil\AccessBundle\PhilAccessBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Doctrine DBAL 4.5 ou
une version plus récente. Les voters exigent SecurityBundle ; les fonctions Twig, TwigBundle ; les composants, Symfony
UX Twig Component et les recettes du kit PHIL UX ; le voter d'EasyAdmin, EasyAdmin 5.8 ou une version plus récente.
Chaque intégration n'est enregistrée que lorsque son bundle est activé.

## Les routes

Les formulaires de « voir comme ce membre » envoient leurs données à deux routes, importées sous un préfixe, derrière
le pare-feu :

```yaml
# config/routes/phil_access.yaml
phil_access:
    resource: '@PhilAccessBundle/config/routes.php'
    prefix: /_access
```

Elles vérifient un jeton CSRF sans état, `phil_access_view_as`, que le bundle ajoute à `framework.csrf_protection` ; un
site dont la protection CSRF est désactivée est arrêté à la compilation par un message qui dit quoi changer.

## La table

Les tuples vivent dans la table `phil_relation_tuple` de la connexion Doctrine `default`. Avec l'ORM, le bundle ajoute
la table au schéma qu'il génère, de sorte qu'une migration la crée :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Planifiez `phil:access:purge` — une fois par jour suffit — pour supprimer les tuples dont l'expiration est passée ; ils
ne comptent déjà plus pour rien.

## Les recettes du kit

La bannière et le résumé dessinent les recettes `Alert`, `Badge`, `Button` et `Table` :

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```
