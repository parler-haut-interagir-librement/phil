<!-- phil-dev source-sha256=9d93c8d12108863ed74737aa6be3ea2245329c51e988e52ffe5116ad5669c196 -->
# Référence

## Bundle

| Classe ou fichier | Rôle |
|---|---|
| `PhilHelpdeskBundle` | Le bundle : sa configuration `phil_helpdesk`, le type de notification déclaré à phil/notifications-bundle, le jeton CSRF sans état |
| `config/routes.php` | Les routes des pages des demandeurs |
| `config/routes_agents.php` | Les routes du bureau des agents |
| `recipe/0.1` | La recette Flex : le bundle, une file et les routes |

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\Ticket` | Une demande : sa conversation, sa file, son statut, son demandeur, son responsable et ses instants |
| `Domain\ValueObject\QueueName` | Le nom d'une file |
| `Domain\Ticket\Status` | `New`, `Open`, `Waiting`, `Resolved` ou `Closed` |
| `Domain\Repository\TicketRepository` | Le dépôt |
| `Domain\Exception\HelpdeskException` | Ce qu'est chaque échec du service d'assistance |
| `Domain\Exception\InvalidQueue` | Une file mal formée ou non déclarée |
| `Domain\Exception\TicketNotFound` | Une demande qui n'existe pas ou qui n'est pas pour la personne qui la demande |
| `Domain\Exception\TransitionRefused` | Un changement de statut que le workflow n'autorise pas |
| `Domain\Exception\CannedReplyNotFound` | Une réponse type non proposée pour la demande |

## Application

| Classe | Rôle |
|---|---|
| `Application\Helpdesk` | Le point d'entrée : ouvrir, répondre, retirer, le bureau, répondre en tant qu'agent, réponses types, assigner, déplacer, transitions |
| `Application\TicketWorkflow` | La machine à états `phil_helpdesk_ticket` et son magasin de marquage |
| `Application\Queues`, `Queue` | Les files de la configuration |
| `Application\CannedReply` | Une réponse type proposée : son identifiant et son libellé |
| `Application\NoCannedReplies` | Aucune réponse type |
| `Application\Contract\QueueAccess` | Si la personne connectée travaille une file |
| `Application\Contract\CannedReplies` | Les réponses types d'une demande, et leur texte |
| `Application\Event\TicketOpened`, `TicketAssigned` | Les événements, par identifiants |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\HelpdeskTables` | La table, ajoutée à un `Schema` |
| `Infrastructure\Doctrine\DbalTicketRepository` | Le dépôt DBAL |
| `Infrastructure\Doctrine\SchemaListener` | Ajoute la table au schéma que génère l'ORM |
| `Infrastructure\Security\RoleQueueAccess` | Les agents d'une file par rôle |
| `Infrastructure\Twig\TwigCannedReplies` | Les réponses types de la configuration |
| `Infrastructure\Twig\HelpdeskExtension` | `phil_helpdesk_kit()` et `phil_helpdesk_name()` |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit |
| `Infrastructure\Http\RequesterController` | Les pages des demandeurs |
| `Infrastructure\Http\AgentController` | Le bureau des agents, aussi greffé sur EasyAdmin |
| `Infrastructure\Http\HelpdeskRequest` | La vérification CSRF sans état des formulaires |
| `Infrastructure\EasyAdmin\HelpdeskAdmin` | Le lien du menu d'un tableau de bord, et les routes et la mise en page dans une interface d'administration |
| `Infrastructure\Notifications\NotifyAssignee` | Avertit un agent d'une demande qui lui est assignée |
| `Infrastructure\Privacy\HelpdeskPrivacy` | L'exportation et l'effacement |
| `Infrastructure\DependencyInjection\HelpdeskServices` | Les services de la configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Lit le sous-répertoire des composants du Toolkit |

## Gabarits et traductions

| Fichier | Rôle |
|---|---|
| `templates/layout.html.twig` | La mise en page par défaut, avec les blocs `title` et `main` |
| `templates/easyadmin/layout.html.twig` | La mise en page dans une interface d'administration EasyAdmin |
| `templates/requests.html.twig`, `new.html.twig`, `request.html.twig` | Les pages des demandeurs |
| `templates/desk.html.twig`, `ticket.html.twig` | Le bureau des agents |
| `templates/_status.html.twig` | Le statut d'une demande, la recette `Badge` |
| `translations/phil_helpdesk.en.php`, `phil_helpdesk.fr.php` | Les libellés |
