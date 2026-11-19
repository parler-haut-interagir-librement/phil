<!-- phil-dev source-sha256=249582b09015e22c6b02716bf140b178c4c8a9b6ef9abc377d9b5866ca16bf51 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente avec son composant Workflow, Doctrine DBAL
4 avec DoctrineBundle, et phil/conversation-bundle, installé et configuré avec son service de gestion de clés — voyez
son guide d'installation. Pour les pages : Twig, Symfony UX Twig Component, Routing, la protection CSRF de Symfony
Security, et les recettes `Button`, `Badge`, `Input`, `NativeSelect` et `Textarea` du kit. Facultatifs : Symfony
Security, pour les agents nommés par rôle ; l'ORM Doctrine, pour la table ajoutée au schéma qu'il génère ;
EasyAdminBundle avec `twig/extra-bundle` ; `phil/notifications-bundle` et `phil/privacy-bundle`.

## Activer le bundle

```sh
composer require phil/helpdesk-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ConversationBundle\PhilConversationBundle::class => ['all' => true],
    Phil\HelpdeskBundle\PhilHelpdeskBundle::class => ['all' => true],
];
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_helpdesk.yaml`, avec une
file, `general`, dont les agents détiennent `ROLE_SUPPORT`, et importe les pages des demandeurs sous `/help` et le
bureau des agents sous `/support-desk` dans `config/routes/phil_helpdesk.yaml`. La recette se trouve dans le dépôt de
recettes de PHIL, lu une fois que l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans cela,
enregistrez le bundle et écrivez la configuration à la main.

## La table

Le bundle garde une table par DBAL, `phil_helpdesk_ticket`, sur la connexion que nomme `phil_helpdesk.connection`. Avec
l'ORM Doctrine, elle est ajoutée au schéma qu'il génère pour cette connexion, de sorte que les migrations de
l'application la créent, à côté des tables des conversations :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, `HelpdeskTables::configure()` l'ajoute à un `Schema`, comme `ConversationTables::configure()` le fait pour
les conversations.

| Colonne | Contenu |
|---|---|
| `conversation_id` | La conversation que la demande est, sa clé primaire |
| `queue` | Le nom de sa file |
| `status` | Sa place dans le workflow : `new`, `open`, `waiting`, `resolved` ou `closed` |
| `requester` | L'identifiant de son demandeur, nul une fois ses données effacées |
| `assignee` | L'identifiant de son agent, ou nul |
| `opened_at`, `updated_at`, `closed_at` | Quand elle a été ouverte, changée en dernier et fermée, en UTC |

## Les routes

```yaml
# config/routes/phil_helpdesk.yaml
phil_helpdesk:
    resource: '@PhilHelpdeskBundle/config/routes.php'
    prefix: /help

phil_helpdesk_agents:
    resource: '@PhilHelpdeskBundle/config/routes_agents.php'
    prefix: /support-desk
```

Placez les deux préfixes derrière le pare-feu de l'application : sans participant connecté, les pages sont refusées, et
le bureau est refusé à une personne qui ne travaille aucune file.

## EasyAdmin

Avec EasyAdminBundle enregistré, le bureau est aussi greffé sur chaque tableau de bord, par `#[AdminRoute]`, sous des
routes qu'EasyAdmin nomme d'après celles du tableau de bord — `admin_helpdesk_desk` sous un tableau de bord dont la
route est `admin` — et son rendu est produit dans sa mise en page. Les pages appellent les mêmes services qu'hors de
l'interface d'administration. Le menu du tableau de bord y renvoie :

```php
use Phil\HelpdeskBundle\Infrastructure\EasyAdmin\HelpdeskAdmin;

public function configureMenuItems(): iterable
{
    yield HelpdeskAdmin::menuItem('admin');
}
```

L'application n'importe `routes_agents.php` que si elle veut aussi le bureau hors de l'interface d'administration.

## Les recettes du kit

Les pages dessinent leurs boutons, leurs statuts, leurs champs et leurs zones de texte avec les recettes du kit,
installées par le Symfony UX Toolkit et employées telles quelles :

```sh
php bin/console ux:install button badge input native-select textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le bundle lit le sous-répertoire des composants dans la configuration du Toolkit ; `phil_helpdesk.kit_subdirectory` en
nomme un autre.
