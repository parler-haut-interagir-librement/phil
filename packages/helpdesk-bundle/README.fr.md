<!-- phil-dev source-sha256=9c11b3dbc5a107cd435855d1e97c66e8f0608a7d54cd9476c0f48fd31870dfee -->
# phil/helpdesk-bundle

**Un service d'assistance pour Symfony : des demandes qui sont des conversations, avec des files, des statuts et des réponses types.**

phil/helpdesk-bundle donne un service d'assistance à une application. Un membre demande de l'aide : il choisit une
file, écrit un sujet et sa question, et la demande s'ouvre. Chaque demande est une conversation de
phil/conversation-bundle — scellée au repos, lue et non lue, notifiée — ouverte par le seul demandeur, avec une file,
un statut et un responsable. Les agents d'une file lisent ses demandes à leur bureau, répondent dans leurs propres mots
ou avec une réponse type, prennent une demande ou l'assignent, la déplacent vers une autre file, la résolvent et la
ferment ; quand un agent répond, il rejoint la conversation et la demande attend le demandeur, et quand le demandeur
répond, elle revient aux agents.

Le statut passe par une machine à états de Symfony Workflow, `phil_helpdesk_ticket` : nouvelle, ouverte, en attente,
résolue, fermée. Ses événements sont ceux du composant : une application retient une transition avec une garde ou en
suit une avec un écouteur. Les files et les rôles de leurs agents viennent de la configuration, vérifiés par le
vérificateur d'autorisation de Symfony Security, ou par le `QueueAccess` de l'application. Les réponses types sont des
gabarits Twig de l'application, écrits pour la demande, proposés dans les files qu'ils nomment ; un adaptateur vers les
gabarits de contenu de l'application les remplace par le port `CannedReplies`.

Le bundle convient à un site qui répond à ses membres : le service à la clientèle d'une boutique, l'assistance d'un
service en ligne, les bénévoles d'une association. Les pages des demandeurs et le bureau des agents fonctionnent sans
JavaScript et dessinent leurs boutons, leurs statuts, leurs champs et leurs zones de texte avec les recettes du kit,
installées par le Symfony UX Toolkit ; avec EasyAdminBundle, le bureau est aussi greffé sur chaque tableau de bord, dans
sa mise en page, par les mêmes services.

Le bundle garde sa table par DBAL, sans l'ORM, afin qu'une longue file se lise par page. phil/notifications-bundle et
phil/privacy-bundle sont facultatifs : avec le premier, un agent est averti d'une demande que quelqu'un d'autre lui a
assignée ; avec le dernier, les demandes participent à l'exportation et à l'effacement des données d'un membre. Les
services ne gardent aucun état entre les requêtes et s'emploient sans risque dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Des demandes qui sont des conversations de phil/conversation-bundle, ouvertes par le seul demandeur, avec une file, un statut et un responsable | Implémenté et testé |
| Le workflow `phil_helpdesk_ticket` : la réponse de l'agent attend le demandeur, la réponse du demandeur ramène la demande | Implémenté et testé |
| Des files et leurs agents par rôle, par `QueueAccess` ; prendre, assigner, déplacer, résoudre, rouvrir, fermer | Implémenté et testé |
| Réponses types : des gabarits Twig de l'application par file, par le port `CannedReplies` | Implémenté et testé |
| Le dépôt DBAL, le schéma par `Schema::editor()`, instants en UTC ; MySQL 8.4, PostgreSQL 17 et SQLite | Implémenté et testé |
| Les pages des demandeurs et le bureau des agents, avec les recettes du kit, en anglais et en français | Implémenté et testé |
| Le bureau greffé sur les tableaux de bord d'EasyAdmin | Implémenté et testé |
| La notification d'une assignation par `phil/notifications-bundle` | Implémenté et testé |
| L'exportation et l'effacement des demandes d'un membre par `phil/privacy-bundle` | Implémenté et testé |
| Un adaptateur de `CannedReplies` vers les gabarits de contenu de l'application | Par le port, par l'application |
| La recette Flex : le bundle, une file et les routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la table, les routes, les recettes du kit, EasyAdmin, la recette. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Demandes](doc/fr/requests.md) | Ouvrir, répondre, le bureau, assigner, déplacer, le workflow et ses événements, les pages. |
| [Réponses types](doc/fr/canned-replies.md) | Les gabarits de la configuration, et le port qui les remplace. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, le workflow, les événements, les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et le test du code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/helpdesk-bundle
php bin/console ux:install button badge input native-select textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_helpdesk.yaml
phil_helpdesk:
    queues:
        billing: { label: Billing, roles: [ROLE_BILLING] }
        tech: { label: Technical support, roles: [ROLE_TECH] }
    canned_replies:
        thanks: { label: Thanks for your patience, template: helpdesk/thanks.txt.twig }
```

```yaml
# config/routes/phil_helpdesk.yaml
phil_helpdesk:
    resource: '@PhilHelpdeskBundle/config/routes.php'
    prefix: /help
phil_helpdesk_agents:
    resource: '@PhilHelpdeskBundle/config/routes_agents.php'
    prefix: /support-desk
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente avec son composant Workflow, Doctrine DBAL
4, et phil/conversation-bundle avec son service de gestion de clés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
