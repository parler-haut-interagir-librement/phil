<!-- phil-dev source-sha256=6d57afd86998f7e23e74e1af8b17f98a3d0388b43764840f931a35edaf577bbc -->
# Configuration

La configuration du bundle se trouve sous `phil_organizations`. Seule `invitations.hash_key` est obligatoire.

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    store: dbal                    # dbal, memory, or the id of an OrganizationStore and UnitStore service
    connection: default            # the DBAL connection of the tables
    clock: clock                   # a PSR-20 clock, symfony/clock's by default
    slugger: ~                     # a phil/slug Slugger; a transliterating slugger when null
    member_of_user: ~              # a MemberOfUser; the user identifier when it is a stable identifier
    recommended_owners: 2
    roles:
        billing: { permissions: [billing.read, billing.pay] }
        admin: { permissions: [billing.read] }       # adds to the built-in admin
    team_roles:
        reviewer: { permissions: [review.approve] }
    unit_roles:
        instructor: { permissions: [courses.assign] }
    units:
        single_membership: false
        layout: '@PhilOrganizations/units/layout.html.twig'
        kit_subdirectory: ~        # the subdirectory of ux_toolkit when null
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
        default_lifetime: 7        # days
        max_lifetime: 30           # days
        default_uses: 1
        max_uses: 100
        purge_retention: 30        # days
    domain_join:
        enabled: false
        roles: [member]
    access:
        enabled: ~                 # null: whenever phil/access-bundle is installed
        organization_kind: organization
        team_kind: team
        unit_kind: unit            # null: the units stay out of the access model
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `dbal` | Où les organisations et leurs unités sont conservées : `dbal`, les huit tables d'une connexion DBAL ; `memory`, pour les tests ; ou l'identifiant d'un service qui implémente `OrganizationStore`, et `UnitStore` pour les unités — sans lui, les unités sont laissées de côté. |
| `connection` | `default` | La connexion DBAL des tables. |
| `clock` | `clock` | L'horloge PSR-20 dont est lu chaque instant. |
| `slugger` | `null` | Le `Slugger` de `phil/slug` qui produit les *slugs* à partir des noms ; un `TransliteratingSlugger` quand la valeur est nulle. |
| `member_of_user` | `null` | Le `MemberOfUser` auquel le *voter* demande quel membre est un utilisateur connecté ; par défaut, l'identifiant de l'utilisateur, quand c'est un identifiant stable. |
| `recommended_owners` | `2` | Le nombre de propriétaires recommandé par organisation : en avoir moins donne un avertissement de `phil:organizations:show` et de `phil:organizations:check-owners`, jamais un refus. |
| `roles` | `{}` | Les rôles d'une organisation en plus de `owner`, `admin` et `member`, chacun avec ses permissions ; listées sous `admin` ou `member`, les permissions s'ajoutent à celles de ces rôles intégrés. `owner` ne peut pas être déclaré : il détient toutes les permissions. |
| `team_roles` | `{}` | Les rôles d'une équipe en plus de `maintainer` et `member`, chacun avec ses permissions ; `organization` ne peut pas être un rôle d'équipe. |
| `unit_roles` | `{}` | Les rôles d'une unité en plus de `manager` et `member`, chacun avec ses permissions, qui valent dans l'unité et dans chaque unité en dessous ; listées sous un rôle intégré, les permissions s'y ajoutent. Voir [Héritage des rôles](role-inheritance.md). |
| `units.single_membership` | `false` | Si un membre appartient à une seule unité d'une organisation au plus. |
| `units.layout` | `@PhilOrganizations/units/layout.html.twig` | Le gabarit que prolongent les pages d'administration des unités ; il remplit les blocs `title` et `main`. |
| `units.kit_subdirectory` | `null` | L'endroit où les recettes du kit sont installées sous le répertoire des composants ; le sous-répertoire de la configuration `ux_toolkit` quand la valeur est nulle. |
| `invitations.hash_key` | obligatoire | Les clés de l'HMAC-SHA-256 sous lequel chaque jeton est conservé, d'au moins 32 octets chacune : une clé, ou une liste dont la première clé calcule l'empreinte des nouveaux jetons et dont les autres retrouvent encore les invitations créées sous elles, huit au plus. Un fichier de configuration ultérieur remplace la liste au lieu de s'y ajouter. |
| `invitations.default_lifetime` | `7` | Les jours que dure une invitation quand son créateur n'indique pas d'expiration. |
| `invitations.max_lifetime` | `30` | Le plus grand nombre de jours qu'une invitation peut durer, au moins `default_lifetime`. |
| `invitations.default_uses` | `1` | Les utilisations qu'une invitation permet quand son créateur ne dit rien. |
| `invitations.max_uses` | `100` | Le plus grand nombre d'utilisations qu'une invitation peut permettre, au moins `default_uses`, au plus 10 000. |
| `invitations.purge_retention` | `30` | Les jours pendant lesquels une invitation expirée ou révoquée est conservée avant que `phil:organizations:purge-invitations` la supprime. |
| `domain_join.enabled` | `false` | Si un membre dont l'adresse vérifiée se trouve dans le domaine vérifié d'une organisation peut la rejoindre. |
| `domain_join.roles` | `[member]` | Les rôles avec lesquels un membre adhère par le domaine ; jamais `owner`. |
| `access.enabled` | `null` | Si les adhésions et les rôles sont publiés comme relations et tuples de `phil/access-bundle` : `null` les publie dès que ce bundle est enregistré. |
| `access.organization_kind` | `organization` | Le genre des organisations dans le modèle d'accès. |
| `access.team_kind` | `team` | Le genre des équipes dans le modèle d'accès. |
| `access.unit_kind` | `unit` | Le genre des unités dans le modèle d'accès ; `null` les en exclut, comme le fait un magasin qui ne garde aucune unité. |

Un rôle compte de 1 à 64 lettres minuscules, chiffres ou `_`, et commence par une lettre ; une permission compte de 1 à
128 lettres minuscules, chiffres, `_`, `.` ou `-`, et commence par une lettre. La configuration est refusée quand l'un
d'eux n'a pas cette forme.
