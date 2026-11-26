<!-- phil-dev source-sha256=81beb862b0fbe9b1928eb8f878955b429b3a1316e8897732b5c74c0ef5ceed25 -->
# Installation

```sh
composer require phil/cors-bundle
```

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_cors.yaml`, une règle pour `^/api/`
dont les origines correspondent à l'expression régulière de la variable d'environnement `CORS_ALLOW_ORIGIN`, ajoutée à
`.env` avec les origines locales `http://localhost` et `http://127.0.0.1` sur n'importe quel port. Sans Flex,
enregistrez le bundle dans `config/bundles.php` :

```php
Phil\CorsBundle\PhilCorsBundle::class => ['all' => true],
```

Écrivez ensuite les règles de vos chemins, comme l'explique [Configuration](configuration.md). Sans règle, le bundle ne
répond à rien et n'ajoute aucun en-tête.

Le paquet exige PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente : `symfony/config`,
`symfony/dependency-injection`, `symfony/http-foundation` et `symfony/http-kernel`.
