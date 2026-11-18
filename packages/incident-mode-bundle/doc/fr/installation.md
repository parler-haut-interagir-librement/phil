<!-- phil-dev source-sha256=25cc64029b8d5e3ede88a405d6fa84b2b6c385f6d9432ef5d00cad7a397a1df4 -->
# Installation

```sh
composer require phil/incident-mode-bundle
```

Le bundle a besoin de PHP 8.5 ou d'une version plus récente avec `ext-intl`, de Symfony 8.2 ou d'une version plus
récente, de Doctrine DBAL 4.5, de Twig avec les composants Twig de Symfony UX, et de `phil/incident-mode` 0.2. Symfony
Flex enregistre le bundle et copie sa recette : la configuration dans `config/packages/phil_incident_mode.yaml` et les
routes dans `config/routes/phil_incident_mode.yaml`. Sans Flex, enregistrez-le :

```php
// config/bundles.php
return [
    // …
    Phil\IncidentModeBundle\PhilIncidentModeBundle::class => ['all' => true],
];
```

Le bundle utilise ces paquets lorsqu'ils sont installés :

| Paquet | Pour |
|---|---|
| `doctrine/orm` | La table ajoutée au schéma que génère l'ORM, de sorte que `doctrine:migrations:diff` écrit sa migration |
| `symfony/console` | Les commandes `phil:incident:*` |
| `symfony/form` | L'option `suspended_during` des formulaires |
| `symfony/messenger` | Le message différé qui lève chaque incident à sa fin |
| `symfony/scheduler` | Le balayage récurrent pour les messages qu'un transport a perdus |
| `phil/ux-kit`, ou le kit Shadcn de Symfony UX Toolkit avec `phil/ux-kit-shadcn` | Les recettes que dessinent les pages |
| `phil/audit-bundle` | Chaque début, prolongation et levée écrit dans sa piste |
| `phil/privacy-bundle` | Les incidents d'un membre dans l'export et l'effacement de ses données |

## La table

Les incidents vivent dans la table `phil_incident_activation` de la connexion `phil_incident_mode.connection`, la
connexion `default` sauf si la configuration en nomme une autre. Avec l'ORM, l'`IncidentSchemaListener` de la
bibliothèque ajoute la table au schéma que l'ORM génère pour cette connexion, et les migrations l'écrivent :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Quand une version de la bibliothèque ajoute une colonne ou un index, `doctrine:migrations:diff` écrit la migration qui
l'ajoute. Une application sur DBAL sans l'ORM crée la table dans l'une de ses propres migrations avec
`Phil\IncidentMode\Dbal\IncidentTables::configure($schema)`, qui ajoute la table à un schéma qui en est dépourvu.

## Les routes

La recette importe les routes de l'administration sous `/admin/incidents` et celles de la page d'état sous `/status` ;
changez les préfixes à cet endroit. L'administration doit se trouver derrière le pare-feu de l'application :

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/incidents, roles: ROLE_ADMIN }
```

L'administration vérifie aussi elle-même le rôle de `phil_incident_mode.admin.role`.

## Le kit

Les pages dessinent les recettes `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table` et `Textarea`.
Installez-les depuis le kit PHIL UX, ou depuis le kit Shadcn de Symfony UX Toolkit, qui portent les mêmes noms :

```sh
for recipe in alert badge button field input native-select table textarea; do
    php bin/console ux:install "$recipe" --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

Avec Tailwind, installez les mêmes recettes avec `--kit=shadcn` ; `phil/ux-kit-shadcn` ajoute les recettes de PHIL qui
manquent à ce kit.

Les recettes se trouvent sous le sous-répertoire des composants de la configuration `ux_toolkit`. Dans un environnement
où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans `phil_incident_mode.kit_subdirectory`.

## Les transports

Acheminez le message différé vers un transport qui prend en charge les délais — Doctrine, Redis, AMQP ; pas `sync` — et
consommez le transport du balayage récurrent, comme le montre [Levée automatique](automatic-lifting.md) :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

```sh
php bin/console messenger:consume async scheduler_incident_mode
```

## Le bundle de modération

`phil/moderation-bundle` déclare lui-même les services de la bibliothèque, avec ses propres pages, quand la
bibliothèque est installée. Une application qui enregistre les deux bundles obtient les services de la bibliothèque du
bundle enregistré en dernier, et les pages de chaque bundle ; ce bundle est l'intégration de la bibliothèque pour toute
application.
