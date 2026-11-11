<!-- phil-dev source-sha256=4d1df7cf0851c4df8c68786b3838bffce146e456660cc69ff91ec163513e4b1b -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toutes | Les messages coupés en caractères |
| `phil/url` | ^0.1.3 | Les adresses du web lues comme un navigateur les lit |
| `symfony/config` | ^8.2 | La clé de configuration |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle et la configuration automatique |
| `symfony/http-foundation` | ^8.2 | La requête dans laquelle est lu l'élément courant |
| `twig/twig` | facultatif | Les fonctions et les gabarits |

```sh
composer require phil/menu-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\MenuBundle\PhilMenuBundle::class => ['all' => true],
];
```

Les services qui implémentent `MenuBuilder` ou `MenuDecorator` sont trouvés quand les services de l'application sont
configurés automatiquement, comme ceux de Symfony le sont par défaut.

## Configuration

Une seule clé, `allow_localhost`, `false` par défaut, qui est aussi le paramètre de conteneur
`phil_menu.allow_localhost`. `MenuItem::withUrl()` refuse une adresse sur `localhost` ou sous `.localhost` à moins de
recevoir `allowLocalhost: true` ; un constructeur qui mène à un service du poste de développement lui passe le
paramètre :

```yaml
when@dev:
    phil_menu:
        allow_localhost: true

when@test:
    phil_menu:
        allow_localhost: true
```

```php
public function __construct(
    #[Autowire(param: 'phil_menu.allow_localhost')] private bool $allowLocalhost,
) {
}

// in build():
MenuItem::of('mail', 'Mail')->withUrl('http://localhost:8025/', $this->allowLocalhost);
```

Une adresse IP, `http://127.0.0.1:8025/`, reste refusée.
