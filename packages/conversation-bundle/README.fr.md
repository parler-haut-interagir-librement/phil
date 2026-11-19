<!-- phil-dev source-sha256=5bdd49a6959dd6228428a4577dfc8b577cf1dcdf6e457db71b7ed43d3c709027 -->
# phil/conversation-bundle

**Messages privés pour Symfony : conversations entre deux membres ou en groupe, scellées au repos.**

phil/conversation-bundle donne des messages privés à une application. Un membre écrit à un autre, et les deux partagent
une seule conversation directe ; ou un membre démarre un groupe, avec un sujet, et y invite d'autres personnes. Les
participants sont nommés par l'identifiant stable que l'application donne à ses membres, jamais par une adresse
courriel, que le bundle refuse. Chaque sujet et chaque message est scellé au repos par le composant KeyManagement de
Symfony : une clé de données neuve par texte, enveloppée par une clé maîtresse du service de gestion de clés de
l'application, liée à sa ligne, dans un format qui nomme sa version et son algorithme. Une commande de rotation scelle
de nouveau les anciens textes sous une nouvelle clé maîtresse.

Chaque membre décide ce qu'il reçoit : si tout le monde, ses contacts ou personne peut démarrer une conversation avec
lui, et qui il bloque. Chaque conversation garde ce que chaque participant a lu, la boîte de réception compte ce qui
n'est pas lu, et un participant peut mettre une conversation en sourdine ou la quitter. Les pages — la boîte de
réception, une conversation et son formulaire de réponse, le formulaire qui écrit à un membre, les réglages de
réception — fonctionnent sans JavaScript et dessinent leurs boutons, leurs pastilles et leurs zones de texte avec les
recettes du kit, installées par le Symfony UX Toolkit.

Le bundle convient à un site dont les membres se parlent : un club, une communauté, une place de marché entre acheteurs
et vendeurs. Il est aussi le socle sur lequel d'autres paquets s'appuient : un service d'assistance dont les demandes
sont des conversations, un clavardage en direct avec des visiteurs.

Le bundle garde ses tables par DBAL, sans l'ORM, afin qu'une longue conversation et une boîte de réception pleine se
lisent par page. phil/notifications-bundle, Mercure et phil/privacy-bundle sont facultatifs : avec le premier, les
destinataires d'un message sont avertis, sauf ceux qui ont mis la conversation en sourdine ou bloqué l'auteur ; avec
Mercure, les pages ouvertes chargent les nouveaux messages dès qu'ils arrivent ; avec le dernier, les conversations
d'un membre participent à l'exportation et à l'effacement de ses données. Les services ne gardent aucun état entre les
requêtes et s'emploient sans risque dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Conversations directes et de groupe, participants nommés par des identifiants stables, un `@` refusé | Implémenté et testé |
| Sujets et corps scellés au repos par le composant KeyManagement, `pcv1.a256gcm.`, liés à leur ligne | Implémenté et testé |
| La rotation de la clé maîtresse et `phil:conversation:rotate-keys` | Implémenté et testé |
| Réglages de réception : tout le monde, les contacts ou personne, et les blocages | Implémenté et testé |
| Lu et non lu, sourdine, départ, boîte de réception et fil lus par page | Implémenté et testé |
| Dépôts DBAL, le schéma par `Schema::editor()`, instants en UTC ; MySQL 8.4, PostgreSQL 17 et SQLite | Implémenté et testé |
| Les pages : boîte de réception, conversation, écrire à un membre, réglages de réception, avec les recettes du kit, en anglais et en français | Implémenté et testé |
| La notification de chaque message par `phil/notifications-bundle` | Implémenté et testé |
| La mise à jour Mercure de chaque message et son contrôleur Stimulus, un hub injoignable intercepté | Implémenté et testé |
| L'exportation et l'effacement des conversations d'un membre par `phil/privacy-bundle` | Implémenté et testé |
| `ConversationReader`, `startOpen()` et `join()`, pour les paquets bâtis sur le bundle | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le service de gestion de clés, les tables, les routes, les recettes du kit, la recette. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Conversations](doc/fr/conversations.md) | Démarrer, écrire, lire, non lu, sourdine, départ, les réglages de réception et les pages. |
| [Chiffrement au repos](doc/fr/encryption.md) | Le format scellé, son contexte, les clés maîtresses et leur rotation. |
| [Notifications, temps réel et vie privée](doc/fr/integrations.md) | Ce que fait le bundle avec phil/notifications-bundle, Mercure et phil/privacy-bundle. |
| [Bâtir sur le bundle](doc/fr/building-on.md) | `ConversationReader`, les conversations ouvertes et `join()`, pour un service d'assistance ou un clavardage en direct. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements, les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et le test du code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/conversation-bundle
php bin/console ux:install button badge textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=%env(CONVERSATIONS_MASTER_KEY)%'

# config/packages/phil_conversation.yaml
phil_conversation:
    encryption:
        key: conversations

# config/routes/phil_conversation.yaml
phil_conversation:
    resource: '@PhilConversationBundle/config/routes.php'
    prefix: /messages
```

```php
$conversation = $conversations->startDirect(ParticipantId::fromString($me), ParticipantId::fromString($seller), 'Is the lamp still for sale?');
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium`, Symfony 8.2 ou une version plus récente avec son composant
KeyManagement, et Doctrine DBAL 4.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
