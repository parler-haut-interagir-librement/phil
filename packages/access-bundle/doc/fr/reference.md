<!-- phil-dev source-sha256=c6a1d046ba0083dea1d6471d370d244d2452c9dfd967e80e8224ad5569829c4e -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\AccessBundle\PhilAccessBundle` | Le bundle : sa configuration `phil_access` et ses services. |
| `Phil\AccessBundle\Domain\ValueObject\MemberId` | Un membre par un identifiant stable, jamais une adresse courriel. |
| `Phil\AccessBundle\Domain\ValueObject\SharingLevels` | Les niveaux de partage, du plus large au plus étroit. |
| `Phil\AccessBundle\Domain\ValueObject\AccessGroup` | Un groupe d'accès déclaré : points d'entrée et durée. |
| `Phil\AccessBundle\Domain\Status\Visibility` | Tout le monde, membres, administrateurs, propriétaire ; la plus étroite de deux. |
| `Phil\AccessBundle\Domain\Status\EntryPoint` | Achat, invitation, lot, automatisation, défaut. |
| `Phil\AccessBundle\Domain\Exception\AccessException` | Chaque exception du bundle. |
| `Phil\AccessBundle\Domain\Exception\InvalidAccess` | Une valeur ou une configuration contraire aux règles. |
| `Phil\AccessBundle\Application\AccessModel` | Construit le modèle d'accès à partir de la configuration et des contributeurs. |
| `Phil\AccessBundle\Application\Items` | Propriétaires, espaces et visibilités des éléments. Service. |
| `Phil\AccessBundle\Application\Sharing` | Partages avec des membres et des groupes. Service. |
| `Phil\AccessBundle\Application\AccessGroups` | Groupes et leurs adhésions. Service. |
| `Phil\AccessBundle\Application\Spaces` | Membres et administrateurs des espaces. Service. |
| `Phil\AccessBundle\Application\ViewAs` | « Voir comme ce membre ». Service. |
| `Phil\AccessBundle\Application\ItemAccess` | Vérifications, explications et listes pour le sujet de la requête. Service. |
| `Phil\AccessBundle\Application\AccessSubject` | Pour qui les vérifications de la requête sont faites. |
| `Phil\AccessBundle\Application\NoCurrentMember` | Personne n'est connecté, la valeur par défaut sans SecurityBundle. |
| `Phil\AccessBundle\Application\Contract\CurrentMember` | Le membre de la requête. |
| `Phil\AccessBundle\Application\Contract\ViewAsStore` | L'endroit où le membre dont on voit le site est conservé. |
| `Phil\AccessBundle\Application\Contract\AccessibleItem` | Un objet vérifié par la référence de ses tuples. |
| `Phil\AccessBundle\Application\Contract\ModelContributor` | Ajoute des types et des relations au modèle. |
| `Phil\AccessBundle\Application\View\Share` | Un partage : niveau, membre ou groupe, fin. |
| `Phil\AccessBundle\Application\View\Membership` | Un membre d'un groupe et la fin de l'adhésion. |
| `Phil\AccessBundle\Application\Event\MemberJoinedGroup` | Un membre est entré dans un groupe. |
| `Phil\AccessBundle\Application\Event\MemberLeftGroup` | Un membre a été retiré d'un groupe. |
| `Phil\AccessBundle\Application\Event\ViewAsStarted` | Un administrateur a commencé à voir le site comme un membre. |
| `Phil\AccessBundle\Application\Event\ViewAsStopped` | Un administrateur a cessé de voir le site comme un membre. |
| `Phil\AccessBundle\Application\Exception\ViewAsRefused` | « Voir comme ce membre » refusé. |
| `Phil\AccessBundle\Infrastructure\Security\AccessVoter` | `access.<level>` et `access.view_as`. |
| `Phil\AccessBundle\Infrastructure\Security\TokenCurrentMember` | Le membre du jeton de sécurité. |
| `Phil\AccessBundle\Infrastructure\EasyAdmin\EasyAdminAccessVoter` | `EA_ACCESS_ENTITY` et `EA_EXECUTE_ACTION`. |
| `Phil\AccessBundle\Infrastructure\Session\SessionViewAsStore` | Le membre dont on voit le site, dans la session. |
| `Phil\AccessBundle\Infrastructure\Http\ViewAsController` | Les formulaires de « voir comme ce membre ». |
| `Phil\AccessBundle\Infrastructure\Twig\AccessExtension` | `access_can`, `access_visibility`, `access_viewing_as`. |
| `Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent` | `PhilAccess:ViewAsBanner`. |
| `Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent` | `PhilAccess:AccessSummary`. |
| `Phil\AccessBundle\Infrastructure\Twig\KitComponents` | Les noms des recettes du kit. |
| `Phil\AccessBundle\Infrastructure\Console\PurgeCommand` | `phil:access:purge`. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\AccessServices` | Enregistre les services. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | Arrête un conteneur sans protection CSRF. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les recettes d'après le sous-répertoire du Toolkit. |
