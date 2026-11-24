<!-- phil-dev source-sha256=21396d6ea0c192ec7d8a9f46a16b14013aada0f5020246807f5e7fc2c0b55f63 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\FollowBundle\PhilFollowBundle` | Le bundle : sa configuration `phil_follow`, ses types de notification et ses services. |
| `Application\Follows` | Suivre, changer de niveau, cesser de suivre, l'état, les suivis du membre, consulté. |
| `Application\FollowRequests` | Les demandes : lister, accepter, rejeter, retirer. |
| `Application\Mutuals` | Les relations mutuelles. |
| `Application\Followers` | Les abonnés d'un sujet tels qu'un lecteur peut les voir, et le choix de les cacher. |
| `Application\AutoFollow` | Suivre ce qu'un membre crée ou ce à quoi il répond. |
| `Application\Activities` | Publier une activité. |
| `Application\FollowState` | Le suivi propre d'un membre sur un sujet, le suivi hérité et une demande en attente. |
| `Application\FollowerList` | Une page d'abonnés, ou rien quand ils sont cachés. |
| `Application\FollowOutcome` | `Done` ou `Requested`. |
| `Application\FollowTypes` | Les clés des types de notification que le bundle envoie. |
| `Application\SubjectChain` | Un sujet et ses parents, du plus proche au plus lointain, bornés. |
| `Application\RecipientResolver` | Qui, dans une page de suivis, apprend une activité. |
| `Application\NoParents`, `OpenFollowing`, `OpenAccess` | Les valeurs par défaut des ports. |
| `Application\Contract\FollowAccess`, `FollowApproval`, `SubjectParents` | Les ports que l'application écrit. |
| `Application\Message\FanOutActivity`, `PurgeFollowRecords` | Les messages. |
| `Application\Handler\FanOutActivityHandler`, `PurgeFollowRecordsHandler` | Leurs handlers. |
| `Application\Exception\FollowRefused`, `UnknownFollowRequest` | Un changement refusé ; une demande qui n'attend pas. |
| `Domain\Model\Follow`, `FollowRequest`, `Activity` | Les enregistrements. |
| `Domain\Status\FollowLevel`, `RequestKind` | Les niveaux ; suivi ou mutuel. |
| `Domain\ValueObject\EventPreferences`, `MemberPair` | Les niveaux par événement ; deux membres dans l'ordre des octets. |
| `Domain\Identifier` | Les formes des identifiants, des clés et des sujets. |
| `Domain\Exception\FollowException`, `InvalidFollow` | Chaque exception du bundle ; une valeur de la mauvaise forme. |
| `Domain\Contract\*Repository` | Les dépôts des suivis, des demandes, des relations, des activités, des livraisons et de la visibilité. |
| `Infrastructure\Doctrine\Dbal*Repository` | Leurs adaptateurs DBAL. |
| `Infrastructure\Doctrine\FollowTables`, `FollowSchemaListener` | Les tables, et leur place dans le schéma de l'ORM. |
| `Infrastructure\Http\FollowController`, `FollowGuard` | Les formulaires, et leurs vérifications. |
| `Infrastructure\Twig\FollowExtension` | Les fonctions Twig. |
| `Infrastructure\Privacy\FollowPrivacy` | L'export et l'effacement. |
| `Infrastructure\Scheduler\FollowSchedule` | Le calendrier `phil_follow`. |
