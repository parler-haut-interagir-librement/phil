<!-- phil-dev source-sha256=a049a8f1b331a7cfe3b3369cdef11804e7f539b75d876b80484d43916c9f670c -->
# Référence

## Bundle

| Classe ou fichier | Rôle |
|---|---|
| `PhilConversationBundle` | Le bundle : sa configuration `phil_conversation`, le type de notification déclaré à phil/notifications-bundle, le jeton CSRF sans état, le contrôleur servi par AssetMapper |
| `config/routes.php` | Les routes des pages |
| `recipe/0.1` | La recette Flex : le bundle, sa configuration et ses routes |

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\ValueObject\ParticipantId` | L'identifiant stable d'un participant, de 1 à 190 caractères, jamais avec `@` |
| `Domain\ValueObject\ConversationId`, `MessageId` | Un UUID, de version 7 quand le bundle le produit |
| `Domain\ValueObject\InboxEntry` | Une ligne de la boîte de réception : la conversation, la participation, le compte de non lus |
| `Domain\Model\Conversation` | Une conversation : son genre, son sujet, son début et son dernier message |
| `Domain\Model\Message` | Un message : son auteur et son corps, nuls une fois effacés, son instant et sa séquence |
| `Domain\Model\Participation` | La place d'un participant dans une conversation : lecture, sourdine, départ |
| `Domain\Model\ReceptionSettings` | Qui peut démarrer une conversation avec un participant, et qui il a bloqué |
| `Domain\Conversation\Kind` | `Direct` ou `Group` |
| `Domain\Reception\WhoCanWrite` | `Everyone`, `Contacts` ou `Nobody` |
| `Domain\Repository\ConversationRepository`, `ReceptionRepository` | Les dépôts |
| `Domain\Exception\ConversationException` | Ce qu'est chaque échec du domaine |
| `Domain\Exception\InvalidParticipant`, `InvalidConversation` | Un identifiant, un texte ou un groupe refusé |
| `Domain\Exception\NotAParticipant` | Une conversation lue ou écrite par une personne qui n'en fait pas partie, ou qui n'existe pas |
| `Domain\Exception\WritingRefused` | Un message que les réglages de réception refusent |
| `Domain\Exception\DirectConversationExists` | Une conversation directe démarrée alors que ses deux participants en partagent déjà une ; `Conversations` l'attrape et écrit dans celle-là |
| `Domain\Exception\UnreadableMessage` | Un texte scellé qui ne s'ouvre pas |

## Application

| Classe | Rôle |
|---|---|
| `Application\Conversations` | Le point d'entrée : démarrer, répondre, rejoindre, ouvrir, fil, marquer comme lu, sourdine, quitter, boîte de réception, non lu ; implémente `ConversationReader` |
| `Application\Reception` | Les réglages de réception, et si une personne peut démarrer une conversation avec une autre |
| `Application\ConversationLimits` | Les bornes d'un message, d'un sujet, d'un groupe et d'une page |
| `Application\Contract\CurrentParticipant` | Qui lit et écrit dans la requête |
| `Application\Contract\ParticipantDirectory` | Le nom d'un participant sur les pages |
| `Application\Contract\ContactRelation` | Si deux participants sont des contacts |
| `Application\Contract\MessageSealer` | Scelle et ouvre les textes au repos |
| `Application\Contract\ConversationReader` | Les conversations lues sans la vérification du participant |
| `Application\IdentifierDirectory`, `NoContacts` | Les valeurs par défaut de l'annuaire et des contacts |
| `Application\Event\ConversationStarted`, `MessageSent` | Les événements, par identifiants |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\KeyManagement\KeyManagementMessageSealer` | Le sceau au-dessus du composant KeyManagement de Symfony, `pcv1.a256gcm.` |
| `Infrastructure\Doctrine\ConversationTables` | Les cinq tables, ajoutées à un `Schema` |
| `Infrastructure\Doctrine\DbalConversationRepository`, `DbalReceptionRepository` | Les dépôts DBAL |
| `Infrastructure\Doctrine\SchemaListener` | Ajoute les tables au schéma que génère l'ORM |
| `Infrastructure\Doctrine\KeyRotation` | Scelle de nouveau les textes d'une clé maîtresse précédente |
| `Infrastructure\Console\RotateKeysCommand` | `phil:conversation:rotate-keys` |
| `Infrastructure\Http\ConversationController` | Les pages |
| `Infrastructure\Http\ConversationRequest` | La vérification CSRF sans état des formulaires |
| `Infrastructure\Twig\ConversationExtension` | `phil_conversation_kit()` et `phil_conversation_name()` |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit |
| `Infrastructure\Security\SecurityCurrentParticipant` | Le participant tiré de l'utilisateur de Symfony Security |
| `Infrastructure\Notifications\NotifyParticipants` | Notifie les destinataires d'un message |
| `Infrastructure\Notifications\ConversationSubjects` | Nomme une conversation notifiée quand elle est montrée |
| `Infrastructure\Mercure\MercureMessagePublisher` | Publie la mise à jour d'un message |
| `Infrastructure\Privacy\ConversationPrivacy` | L'exportation et l'effacement |
| `Infrastructure\DependencyInjection\ConversationServices` | Les services de la configuration |
| `Infrastructure\DependencyInjection\EncryptionPass` | Arrête le conteneur sans le client de gestion de clés |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Lit le sous-répertoire des composants du Toolkit |

## Gabarits, traductions et ressources

| Fichier | Rôle |
|---|---|
| `templates/layout.html.twig` | La mise en page par défaut, avec les blocs `title` et `main` |
| `templates/inbox.html.twig`, `thread.html.twig`, `_messages.html.twig`, `start.html.twig`, `settings.html.twig` | Les pages |
| `templates/_realtime.html.twig` | Les attributs du contrôleur du temps réel |
| `translations/phil_conversation.en.php`, `phil_conversation.fr.php` | Les libellés |
| `assets/src/thread_controller.js` | Le contrôleur Stimulus qui charge les nouveaux messages |
