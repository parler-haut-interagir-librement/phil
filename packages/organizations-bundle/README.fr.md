<!-- phil-dev source-sha256=2d102aee9ff5b0da13d87c9c74cedee12be216aae8713425e81fdade27fbe890 -->
# phil/organizations-bundle

**Des organisations pour Symfony, destinées à quiconque construit son propre service d'identité et d'accès : membres,
rôles et leurs permissions, dernier propriétaire protégé, équipes, unités en arbre dont les rôles valent en dessous
d'elles, invitations, adhésion par un domaine vérifié et action au nom d'une organisation.**

phil/organizations-bundle conserve les organisations et leurs membres. Un membre est désigné par l'identifiant opaque du
sujet que donne le fournisseur d'utilisateurs de l'application — un ULID, un UUID, le `sub` d'un fournisseur OpenID
Connect —, jamais par une adresse courriel : le bundle fonctionne donc avec n'importe quel fournisseur d'utilisateurs.
Chaque adhésion porte un ou plusieurs **rôles** : `owner`, `admin` et `member` sont intégrés, et la configuration en
déclare d'autres ainsi que les permissions de chacun — les permissions sont de la configuration, les rôles que détient
chaque membre sont des données. Un membre qui gère les membres n'accorde, ne change ou ne retire que des rôles dont il
détient lui-même les permissions, et **le dernier propriétaire ne peut jamais partir, être retiré ni perdre le rôle de
propriétaire** : une organisation a donc toujours quelqu'un qui peut accorder et révoquer. Au moins deux propriétaires
sont recommandés, et une commande liste les organisations qui en ont moins.

Une organisation a des **équipes**, chacune avec ses propres rôles — `maintainer` et `member` sont intégrés, d'autres se
déclarent —, et des **unités** — départements, services, succursales — en arbre : créées, renommées, déplacées avec les
unités qui sont en dessous, un cycle refusé, archivées et restaurées. Un **rôle d'unité** — `manager` et `member` sont
intégrés, d'autres se déclarent — vaut dans son unité et dans chaque unité en dessous ; un membre peut n'appartenir qu'à
une seule unité d'une organisation, quand la configuration le dit. `UnitTree` répond à ce que les autres modules
demandent à l'arbre, et le bundle dessine les pages d'administration des unités avec les recettes du kit PHIL UX. Elle
est active ou **archivée**, en lecture seule jusqu'à sa restauration. Les membres arrivent par ajout, par
des **invitations** limitées dans le temps et en nombre d'utilisations qui portent des rôles — leurs jetons ne sont
conservés que sous forme d'empreinte à clé, et chaque utilisation est comptée par une seule écriture conditionnelle dont
le nombre de lignes touchées est vérifié —, ou, quand la configuration l'active, par le **domaine de courriel** que
l'organisation a vérifié. Un membre peut **agir au nom de l'organisation** : l'organisation est présentée comme l'auteur,
et un événement consigne le véritable auteur à côté, pour une piste d'audit.

Chaque changement s'exécute dans une seule transaction et ses **événements sont émis une fois la transaction validée**,
avec des identifiants seulement. `OrganizationVoter` répond à `is_granted('organization:<permission>', $organization)`,
à `is_granted('organization_role:<role>', $organization)`, ainsi qu'à `unit:<permission>` et `unit_role:<role>` sur une
unité. Avec `phil/privacy-bundle`, un exportateur et un effaceur répondent aux demandes d'accès et d'effacement d'un
membre ; avec `phil/access-bundle`, les adhésions, les rôles et les unités sont publiés comme relations et tuples de
`phil/resource-permissions`, dans la partition du locataire actif, un rôle d'unité valant dans les unités en dessous
comme dans `UnitAccess`.

C'est un bundle Symfony fondé sur Doctrine DBAL, `phil/slug`, `phil/access-links`, PSR-14 et PSR-20 ; la console, la
sécurité de Symfony, Doctrine ORM, Twig, `phil/privacy-bundle` et `phil/access-bundle` sont facultatifs. Les
organisations vivent dans huit tables sur SQLite, MySQL, MariaDB ou PostgreSQL, chaque colonne d'identifiant et de clé
comparée octet par octet, les unités en chemins matérialisés choisis par banc d'essai, testées sur MySQL 8.4 et
PostgreSQL 17 ; un magasin en mémoire sert aux tests. Ses services ne gardent aucun état entre les requêtes et
conviennent à un *worker* de longue durée. En dehors des pages des unités, l'application dessine ses écrans avec les
recettes du kit PHIL UX.

## État

| Partie | État |
|---|---|
| Organisations : nom, *slug* produit par `phil/slug` ou fourni, active ou archivée, domaine de courriel vérifié | Implémenté et testé |
| Adhésions par identifiant opaque de sujet, rôles intégrés et déclarés, permissions tirées de la configuration, octrois bornés par les permissions de l'acteur | Implémenté et testé |
| Le dernier propriétaire protégé, l'organisation verrouillée pendant chaque changement ; la recommandation de deux propriétaires et sa vérification | Implémenté et testé |
| Équipes avec leurs propres rôles, gérées par les gestionnaires d'équipes de l'organisation ou les mainteneurs de l'équipe | Implémenté et testé |
| Unités en arbre, seize niveaux et dix mille unités au plus : créer, renommer, déplacer avec un cycle refusé, archiver et restaurer avec les unités en dessous ; membres, une seule unité par membre quand la configuration le demande | Implémenté et testé |
| Rôles d'unité tirés de la configuration qui valent dans l'unité et en dessous, vérifiés par `UnitAccess` et le *voter* ; `UnitTree` pour les autres modules | Implémenté et testé |
| Pages d'administration des unités sur les recettes du kit PHIL UX, jetons CSRF sans état | Implémenté et testé |
| Invitations limitées dans le temps et en utilisations, portant des rôles, jetons conservés en empreinte sous des clés en rotation, chaque utilisation atomique, révocables, refusées quand leur créateur ne peut plus accorder leurs rôles | Implémenté et testé |
| Adhésion par un domaine de courriel vérifié, désactivée par défaut | Implémenté et testé |
| Action au nom d'une organisation, véritable auteur consigné | Implémenté et testé |
| Événements de domaine émis après la validation, identifiants seulement | Implémenté et testé |
| `OrganizationVoter` pour les permissions et les rôles | Implémenté et testé |
| Magasin Doctrine DBAL sur SQLite, MySQL et PostgreSQL, collation binaire sur les colonnes de clé, écouteur de schéma ; magasin en mémoire | Implémenté et testé |
| Commandes pour lister, afficher, vérifier les propriétaires et purger les invitations | Implémenté et testé |
| Exportateur et effaceur pour `phil/privacy-bundle` ; relations et tuples pour `phil/access-bundle`, partitionnés par locataire, unités et rôles hérités compris | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la clé des invitations, les tables, les pages. |
| [Mise à niveau](doc/fr/upgrading.md) | D'une version à la suivante. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Membres et rôles](doc/fr/members-and-roles.md) | Les membres, les rôles intégrés et déclarés, les permissions, la borne des octrois, les propriétaires, l'archivage. |
| [Équipes](doc/fr/teams.md) | Les équipes, leurs rôles, qui les gère. |
| [Unités](doc/fr/units.md) | Les unités en arbre, leurs membres, qui change quoi, les questions des autres modules, comment l'arbre est stocké, les pages. |
| [Héritage des rôles](doc/fr/role-inheritance.md) | Les rôles d'unité, ce qu'un membre détient dans une unité et en dessous, le *voter*. |
| [Invitations](doc/fr/invitations.md) | Créer, accepter, révoquer et purger des invitations ; adhérer par un domaine vérifié. |
| [Agir au nom de l'organisation](doc/fr/on-behalf.md) | Agir comme l'organisation pendant que le véritable auteur est consigné. |
| [Événements](doc/fr/events.md) | Chaque événement, quand il est émis et ce qu'il porte. |
| [Intégrations](doc/fr/integrations.md) | Le voter, `phil/privacy-bundle`, `phil/access-bundle`, `phil/audit-bundle`. |
| [Console](doc/fr/console.md) | Les quatre commandes. |
| [Notes de sécurité](doc/fr/security.md) | Ce dont le bundle se défend, et ce qu'il laisse à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les contrats à implémenter, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et comment tester une application. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service publics du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\OrganizationsBundle\PhilOrganizationsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
    roles:
        billing: { permissions: [billing.read, billing.pay] }
```

```php
$acme = $organizations->create('Acme Guild', MemberId::fromString($user->id));      // its creator owns it
$members->add($acme->id, $owner, MemberId::fromString($other->id), ['admin']);
$issued = $invitations->create($acme->id, $owner, ['member'], maxUses: 5);         // $issued->token, shown once
$invitations->accept($token, MemberId::fromString($newcomer->id));
$sales = $units->create($acme->id, $owner, 'Sales');
$east = $units->create($acme->id, $owner, 'East', $sales->id);
$units->addMember($sales->id, $owner, MemberId::fromString($other->id), ['manager']);   // manages East too
```

```twig
{% if is_granted('organization:members.invite', organization) %}…{% endif %}
{% if is_granted('unit:unit.manage', unit) %}…{% endif %}
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-filter` et `ext-mbstring`, Symfony 8.2 ou une version plus récente,
Doctrine DBAL 4.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
