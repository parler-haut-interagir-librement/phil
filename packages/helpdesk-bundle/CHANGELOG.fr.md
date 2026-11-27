<!-- phil-dev source-sha256=454be13fb6d098b87d0d96d3c3e1914ccd097a7f22179a95be06985f06032670 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/helpdesk-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/notifications-bundle` 0.4 et `phil/privacy-bundle` 0.3, les versions
  mineures de leurs branches principales et aucune plus ancienne, pour que le paquet s'installe seul à côté de ces
  bundles.
- Exige la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6, `symfony/event-dispatcher-contracts` 3.8), and of
  each development requirement (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, …).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.
- Les prérequis de développement prennent `easycorp/easyadmin-bundle` 5.10.4 ou plus récent, la version qui corrige
  GHSA-f7rw-7wx5-3c54 : un lien vers une route, comme les liens de menu de ce bundle, passait ses `routeParams` au
  contrôleur de cette route comme arguments.

### Sécurité

- Entre en conflit avec `easycorp/easyadmin-bundle` sous 5.10.4, la version qui corrige GHSA-f7rw-7wx5-3c54 : une
  application ne peut pas installer ce bundle à côté d'un EasyAdmin vulnérable, qui reste facultatif.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- Des demandes qui sont des conversations de phil/conversation-bundle, ouvertes par le demandeur seul, avec une file, un
  statut et un responsable ; un agent qui répond ou qui est assigné rejoint la conversation.
- La machine à états Symfony Workflow `phil_helpdesk_ticket` : nouvelle, ouverte, en attente, résolue, fermée ; la
  réponse d'un agent met la demande en attente du demandeur, la réponse du demandeur la ramène ; prendre, résoudre,
  rouvrir et fermer à la main ; une demande fermée ne prend plus de messages.
- Des files déclarées par configuration avec les rôles de leurs agents, vérifiées par `QueueAccess` ; le guichet filtré
  par file, statut et responsable, lu par pages ; l'assignation, le déplacement entre files.
- Des réponses toutes faites : des gabarits Twig de l'application, écrits pour la demande, offerts par file, derrière le
  port `CannedReplies` qu'un adaptateur vers les gabarits de contenu de l'application remplace.
- `TicketOpened` et `TicketAssigned`, par identifiants seulement.
- Le stockage DBAL, la table de `HelpdeskTables` construite avec `Schema::editor()` et ajoutée au schéma que génère
  l'ORM, les moments en UTC ; testé sur SQLite, MySQL 8.4 et PostgreSQL 17.
- Les pages des demandeurs et le guichet des agents, avec le jeton CSRF sans état `phil_helpdesk`, les recettes
  `Button`, `Badge`, `Input`, `NativeSelect` et `Textarea` du kit, et des libellés en anglais et en français.
- Le guichet greffé sur chaque tableau de bord EasyAdmin par `#[AdminRoute]`, dans sa disposition, avec
  `HelpdeskAdmin::menuItem()`.
- La notification d'un agent à qui une demande est assignée par quelqu'un d'autre, par phil/notifications-bundle.
- L'exportation et l'effacement des demandes d'un membre par les contrats de phil/privacy-bundle.
- La recette Flex dans `recipe/0.1` : le bundle enregistré, une file et les routes.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
