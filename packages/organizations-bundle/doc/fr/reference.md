<!-- phil-dev source-sha256=92dac482d45e6978505c7d7faaa6766dc8e206f97d1c22b17db9b4bf6d238997 -->
# Référence

Chaque type public du paquet, avec son rôle.

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Model\Organization` | Une organisation : identifiant, nom, *slug*, statut, création, archivage, domaine vérifié. |
| `Domain\Model\Membership` | Un membre d'une organisation, avec ses rôles, comment et quand il a adhéré. |
| `Domain\Model\Team` | Une équipe d'une organisation : identifiant, nom, *slug*, création. |
| `Domain\Model\TeamMembership` | Un membre d'une équipe, avec ses rôles d'équipe. |
| `Domain\Model\Unit` | Une unité d'une organisation : identifiant, nom, chemin, statut, création, archivage ; `moveUnder()`. |
| `Domain\Model\UnitMembership` | Un membre d'une unité, avec ses rôles d'unité. |
| `Domain\Model\Invitation` | Une invitation : rôles, utilisations permises et faites, création, expiration, créateur, révocation ; son état à un instant donné. |
| `Domain\ValueObject\UnitPath` | L'emplacement d'une unité : ses ancêtres depuis la racine, puis elle-même, écrit `/root/unit/`, seize niveaux au plus ; la borne de ses descendants. |
| `Domain\ValueObject\OrganizationId`, `TeamId`, `UnitId`, `InvitationId` | Des identifiants : un UUID version 7 quand le bundle en crée un, ou celui de l'application. |
| `Domain\ValueObject\MemberId` | Un membre, par l'identifiant opaque du sujet que donne le fournisseur d'utilisateurs. |
| `Domain\ValueObject\Identifier` | La règle de tout identifiant : de 1 à 128 lettres ASCII, chiffres, `_`, `.` ou `-`, jamais `@`. |
| `Domain\ValueObject\Roles` | De un à seize noms de rôle, triés, sans répétition. |
| `Domain\ValueObject\OrganizationName` | Un nom d'organisation, d'équipe ou d'unité : sans espaces aux extrémités, de 1 à 200 caractères, sans caractère de contrôle. |
| `Domain\ValueObject\EmailDomain` | Un domaine de courriel vérifié, en ASCII minuscule ; le domaine d'une adresse. |
| `Domain\ValueObject\Moment` | Les instants du modèle, à la seconde près et en UTC. |
| `Domain\Status\OrganizationStatus` | `active` ou `archived`, d'une organisation ou d'une unité. |
| `Domain\Status\JoinMethod` | `created`, `added`, `invitation` ou `domain`. |
| `Domain\Status\LeaveReason` | `left`, `removed` ou `erased`. |
| `Domain\Status\InvitationState` | `usable`, `expired`, `revoked` ou `used_up`. |
| `Domain\Event\*` | Les événements, listés dans [Événements](events.md). |
| `Domain\Exception\OrganizationsException` | Ce qu'implémente chaque exception du bundle. |
| `Domain\Exception\InvalidOrganization` | Une valeur que le modèle refuse. |

## Application

| Type | Rôle |
|---|---|
| `Application\Organizations` | Crée, trouve, liste, renomme, archive et restaure des organisations ; consigne leur domaine vérifié. |
| `Application\Members` | Ajoute des membres, change leurs rôles, les retire ; un membre part ; le dernier propriétaire est protégé. |
| `Application\Teams` | Crée, renomme et supprime des équipes ; ajoute des membres, change leurs rôles d'équipe, les retire. |
| `Application\Units` | Crée, renomme, déplace, archive et restaure des unités ; ajoute des membres, change leurs rôles d'unité, les retire. |
| `Application\UnitTree` | Les questions des autres modules : une unité, ses ancêtres, ses enfants, ses descendants et ses membres, les unités d'un membre. |
| `Application\UnitAccess` | Ce qu'un membre peut faire dans une unité, les rôles d'unité au-dessus d'elle compris : `can()`, `hasRole()`, `permissions()`. |
| `Application\Invitations` | Crée, prévisualise, accepte, révoque, liste et purge des invitations. |
| `Application\IssuedInvitation` | Une invitation nouvellement créée, et son jeton, montré une seule fois. |
| `Application\InvitationPolicy` | Les durées de vie et les utilisations des invitations. |
| `Application\DomainJoin` | Adhérer à une organisation par son domaine de courriel vérifié. |
| `Application\OnBehalf`, `OnBehalfContext` | Agir au nom d'une organisation, le véritable auteur étant consigné. |
| `Application\OrganizationAccess` | Ce qu'un membre peut faire dans une organisation : `can()`, `hasRole()`, `permissions()`. |
| `Application\RoleCatalog` | Les rôles et leurs permissions, d'organisations, d'équipes ou d'unités ; `covers()`, la borne des octrois. |
| `Application\Permission` | Les permissions intégrées. |
| `Application\OwnershipCheck` | La recommandation d'au moins deux propriétaires, et les organisations qui sont sous ce seuil. |
| `Application\MemberErasure` | Efface un membre de chaque organisation. |
| `Application\UnitOfWork`, `EventRecorder` | Un changement dans une seule transaction, ses événements émis après la validation. |
| `Application\SlugText` | Les *slugs* des organisations et des équipes, fournis ou produits. |
| `Application\Contract\OrganizationStore` | Où sont conservés les organisations, les membres, les équipes et les invitations. |
| `Application\Contract\UnitStore` | Où sont conservés les unités et leurs membres, à côté des organisations. |
| `Application\Contract\MemberOfUser` | Le membre que représente un utilisateur connecté. |
| `Application\Exception\*` | `UnknownOrganization`, `UnknownTeam`, `UnknownUnit`, `UnknownInvitation`, `UnknownRole`, `NotAMember`, `AlreadyMember`, `LastOwner`, `PermissionDenied`, `OrganizationArchived`, `SlugTaken`, `UnitArchived`, `UnitCycle`, `InvitationNotUsable` et sa `reason`, `DomainJoinRefused`. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Dbal\DbalOrganizationStore` | Le magasin des organisations et des unités sur une connexion Doctrine DBAL ; `configureSchema()`. |
| `Infrastructure\Dbal\OrganizationTables` | Les huit tables, construites pour une plateforme ; `upgrade()` et `downgrade()` pour une migration. |
| `Infrastructure\Dbal\DbalRows` | Les lignes relues sous forme de modèle. |
| `Infrastructure\Doctrine\OrganizationSchemaListener` | Ajoute les tables au schéma que génère l'ORM. |
| `Infrastructure\Memory\InMemoryOrganizationStore` | Le magasin en mémoire, réinitialisé entre les requêtes. |
| `Infrastructure\Security\OrganizationVoter` | `organization:<permission>`, `organization_role:<role>`, `unit:<permission>` et `unit_role:<role>`. |
| `Infrastructure\Http\UnitAdminController` | Les pages d'administration des unités. |
| `Infrastructure\Twig\KitComponents`, `OrganizationsExtension` | Les noms des recettes du kit, et `phil_organizations_kit()`. |
| `Infrastructure\DependencyInjection\UnitServicesPass`, `KitSubdirectoryPass` | Les unités laissées de côté d'un magasin qui n'en conserve aucune, les pages d'une application sans Twig ; le sous-répertoire des composants du Toolkit. |
| `Infrastructure\Security\UserIdentifierMember` | L'identifiant de l'utilisateur comme membre, quand c'est un identifiant stable. |
| `Infrastructure\Console\ListOrganizationsCommand`, `ShowOrganizationCommand`, `CheckOwnersCommand`, `PurgeInvitationsCommand` | Les commandes de [Console](console.md). |
| `Infrastructure\Privacy\OrganizationsPrivacy` | L'exportateur et l'effaceur de `phil/privacy-bundle`. |
| `Infrastructure\Access\OrganizationRelations` | Les genres et les relations ajoutés au modèle d'accès de `phil/access-bundle`. |
| `Infrastructure\Access\MembershipTuples` | Les adhésions et les rôles publiés comme tuples. |
| `Infrastructure\DependencyInjection\OrganizationsServices` | Les services, tirés de la configuration. |
| `PhilOrganizationsBundle` | Le bundle : sa configuration `phil_organizations` et ses services. |

## Les tables

| Table | Une ligne par | Clés |
|---|---|---|
| `phil_organization` | organisation | `id` ; `slug` unique ; `email_domain` |
| `phil_organization_member` | membre d'une organisation, avec ses rôles | `organization_id, member_id` ; `member_id` |
| `phil_organization_member_role` | rôle d'un membre, d'où l'on compte les propriétaires | `organization_id, role, member_id` ; `member_id` |
| `phil_organization_team` | équipe | `id` ; `organization_id, slug` unique |
| `phil_organization_team_member` | membre d'une équipe, avec ses rôles d'équipe | `team_id, member_id` ; `member_id, organization_id` |
| `phil_organization_unit` | unité, avec son parent, son chemin et sa profondeur | `id` ; `organization_id, path` ; `organization_id, parent_id` |
| `phil_organization_unit_member` | membre d'une unité, avec ses rôles d'unité | `unit_id, member_id` ; `member_id, organization_id` |
| `phil_organization_invitation` | invitation | `id` ; `token_hash` unique ; `organization_id, created_at` ; `created_by` ; `expires_at` ; `revoked_at` |

Les identifiants, les *slugs*, les chemins, les rôles, les domaines et les empreintes sont des colonnes ASCII en `ascii_bin` sur MySQL
et MariaDB, en collation `C` sur PostgreSQL ; les instants sont des horodatages Unix.
