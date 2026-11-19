<!-- phil-dev source-sha256=019b80399fc619aad13327b2e1716c747936e706e647c5262f016956c8a2fa43 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium` et `ext-mbstring`, Symfony 8.2 ou une version plus récente avec
son composant KeyManagement, et Doctrine DBAL 4 avec DoctrineBundle. Pour les pages : Twig, Symfony UX Twig Component,
Routing, la protection CSRF de Symfony Security, et les recettes `Button`, `Badge` et `Textarea` du kit. Facultatifs :
l'ORM Doctrine, pour les tables ajoutées au schéma qu'il génère ; la console, pour la commande de rotation ; Symfony
Security, pour le participant courant ; `phil/notifications-bundle`, MercureBundle et `phil/privacy-bundle`.

## Activer le bundle

```sh
composer require phil/conversation-bundle
```

```php
// config/bundles.php
return [
    // …
    Symfony\Component\KeyManagement\KeyManagementBundle::class => ['all' => true],
    Phil\ConversationBundle\PhilConversationBundle::class => ['all' => true],
];
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_conversation.yaml`, qui
nomme la clé maîtresse `%env(PHIL_CONVERSATION_KEY)%`, ajoute `PHIL_CONVERSATION_KEY=conversations` à `.env`, et importe
les pages sous `/messages` dans `config/routes/phil_conversation.yaml`. Le service de gestion de clés doit alors détenir
une clé maîtresse de ce nom, comme ci-dessous. La recette se trouve dans le dépôt de recettes de PHIL, lu une fois que
l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans cela, enregistrez le bundle et écrivez la
configuration à la main.

## Le service de gestion de clés

Les conversations ne sont jamais gardées en clair : `phil_conversation.encryption.key` est obligatoire, et nomme une
clé maîtresse d'un client du composant KeyManagement. Un client local détient lui-même ses clés, depuis
l'environnement :

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=%env(CONVERSATIONS_MASTER_KEY)%'
```

```sh
# 32 random bytes in base64url, kept in the secrets of the application, never in the repository
php -r 'echo rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "="), PHP_EOL;'
```

Le client d'un service infonuagique de gestion de clés y nomme plutôt sa clé ; voyez la documentation du composant. Le
bundle emploie le client par défaut du composant, ou celui que nomme `phil_conversation.encryption.kms`. Quand ce client
n'est pas défini, le conteneur s'arrête et dit quoi changer. Voyez [Chiffrement au repos](encryption.md).

## Les tables

Le bundle garde cinq tables par DBAL, sur la connexion que nomme `phil_conversation.connection`. Avec l'ORM Doctrine,
elles sont ajoutées au schéma qu'il génère pour cette connexion, de sorte que les migrations de l'application les
créent :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, `ConversationTables::configure()` les ajoute à un `Schema`, à partir duquel une migration ou un script écrit
le SQL :

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;

foreach (ConversationTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

| Table | Contenu |
|---|---|
| `phil_conversation` | Une conversation : son UUID, son genre, son sujet scellé et le nom de sa clé maîtresse, son début, l'instant et la séquence de son dernier message et, pour une conversation directe, l'empreinte de ses deux participants, unique |
| `phil_conversation_participant` | Un participant d'une conversation : son identifiant, son arrivée, la séquence du dernier message qu'il a lu, s'il l'a mise en sourdine, son départ |
| `phil_conversation_message` | Un message : sa séquence, son UUID, sa conversation, son auteur, son corps scellé et le nom de sa clé maîtresse, son envoi |
| `phil_conversation_reception` | Qui un participant laisse démarrer une conversation avec lui, quand ce n'est pas tout le monde |
| `phil_conversation_block` | Une personne qu'un participant a bloquée |

Les instants sont gardés en UTC. Le bundle fonctionne sur MySQL 8.4, PostgreSQL 17 et SQLite.

## Les routes

```yaml
# config/routes/phil_conversation.yaml
phil_conversation:
    resource: '@PhilConversationBundle/config/routes.php'
    prefix: /messages
```

Placez le préfixe derrière le pare-feu de l'application : sans participant connecté, les pages sont refusées.

## Les recettes du kit

Les pages dessinent leurs boutons, leurs comptes et leurs zones de texte avec les recettes `Button`, `Badge` et
`Textarea` du kit, installées par le Symfony UX Toolkit et employées telles quelles :

```sh
php bin/console ux:install button badge textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le bundle lit le sous-répertoire des composants dans la configuration du Toolkit ; `phil_conversation.kit_subdirectory`
en nomme un autre.

## Les ressources

Avec AssetMapper et le bundle Stimulus, le contrôleur qui charge les nouveaux messages d'une conversation ouverte est
servi sans étape de construction sous `@phil/conversation-bundle`. Il n'est employé que lorsque
[le temps réel](integrations.md#temps-réel) est activé.
