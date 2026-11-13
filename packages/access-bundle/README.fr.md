<!-- phil-dev source-sha256=9da543a8ba1e7e021e457347424482144720d28d77ab896a904aefd4e377bb85 -->
# phil/access-bundle

**Qui voit et qui peut modifier chaque élément d'un site : visibilité, niveaux de partage, groupes d'accès, accès qui se
termine de lui-même et « voir comme ce membre », décidés par des voters de Symfony Security.**

phil/access-bundle donne à un site Symfony une seule couche d'accès pour ses éléments — pages, documents, événements,
fichiers. Chaque élément a un **propriétaire**, un **espace** — le site, ou l'une de ses parties — et une
**visibilité** : tout le monde, les membres de l'espace, ses administrateurs, ou le propriétaire seul, privée par
défaut. Il est **partagé** avec des membres ou des groupes d'accès à un **niveau de partage** — `manage`, `edit`,
`comment`, `view` par défaut, chacun incluant le suivant —, jusqu'à une date quand on en donne une : le partage se
termine alors de lui-même à cette seconde. Les **groupes d'accès** déclarent leurs **points d'entrée** — un achat, une
invitation, une action en lot d'un administrateur, une automatisation, ou chaque nouveau membre — et la durée d'une
adhésion. Un administrateur de l'espace peut **voir le site comme un membre**, en lecture seule, sans prendre sa
session : chaque début et chaque fin sont journalisés et envoyés comme événements.

Chaque droit et chaque niveau de partage est une relation déclarée de `phil/resource-permissions` : le `view` d'un
élément inclut son `comment`, qui inclut son `edit`, puis son `manage`, que détient son propriétaire ; une visibilité
est un tuple sur `view` donné à tous les visiteurs, aux membres de l'espace ou à ses administrateurs ; un partage est
un tuple donné à un membre ou aux membres d'un groupe. Une vérification s'écrit donc `is_granted('access.edit',
$page)`, la liste de ce qu'un membre voit est une liste bornée du modèle, et *pourquoi* un membre voit un élément est
la chaîne de tuples qui le lui accorde.

`AccessVoter` répond à `access.<level>` pour toute `ResourceReference` ou tout `AccessibleItem`, et à
`access.view_as` ; `EasyAdminAccessVoter` répond à `EA_ACCESS_ENTITY` et `EA_EXECUTE_ACTION` d'EasyAdmin pour les
entités qui sont des `AccessibleItem`. Un membre est toujours nommé par un identifiant stable, jamais par une adresse
courriel. La bannière de « voir comme ce membre » et le résumé de qui a accès sont des composants Twig dessinés avec les
recettes du kit PHIL UX.

## État

| Partie | État |
|---|---|
| Le modèle d'accès : espaces, groupes et éléments, une relation par niveau de partage, étendu par des contributeurs étiquetés | Implémenté et testé |
| Visibilité par élément — tout le monde, membres, administrateurs, propriétaire — privée par défaut, et la plus étroite de deux pour un lien | Implémenté et testé |
| Niveaux de partage, partages avec des membres et des groupes d'accès, partages qui se terminent d'eux-mêmes | Implémenté et testé |
| Groupes d'accès, leurs points d'entrée et leurs adhésions qui se terminent d'elles-mêmes ; groupes comme membres ou administrateurs d'un espace | Implémenté et testé |
| « Voir comme ce membre » : administrateurs seulement, lecture seule, journalisé et envoyé comme événement, démarré et arrêté par de simples formulaires avec un jeton CSRF | Implémenté et testé |
| `AccessVoter` et `EasyAdminAccessVoter`, avec les raisons de chaque vote | Implémenté et testé |
| Les fonctions Twig `access_can`, `access_visibility`, `access_viewing_as` ; les composants `PhilAccess:ViewAsBanner` et `PhilAccess:AccessSummary` sur les recettes du kit | Implémenté et testé |
| Magasin Doctrine DBAL et écouteur de schéma, magasin en mémoire, `phil:access:purge` | Implémenté et testé |
| Organisations et leurs rôles, depuis `phil/organizations-bundle` | Prévu, une fois ce bundle publié |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes et la table. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Le modèle d'accès](doc/fr/model.md) | Les types et les relations que le bundle déclare, et comment y ajouter. |
| [Éléments, visibilité et partage](doc/fr/items-and-sharing.md) | Propriétaires, espaces, visibilités, niveaux de partage et partages qui se terminent d'eux-mêmes. |
| [Groupes d'accès](doc/fr/access-groups.md) | Groupes, points d'entrée, adhésions, groupes par défaut. |
| [Voir comme ce membre](doc/fr/view-as.md) | Qui peut, ce que cela montre, le journal et les événements, la bannière. |
| [Vérifier l'accès](doc/fr/checking.md) | Les voters, les fonctions Twig, lister ce qu'un membre voit, et pourquoi. |
| [EasyAdmin](doc/fr/easyadmin.md) | Le voter sur les permissions d'EasyAdmin, le contrôleur CRUD à portée déclarée, le filtre de locataire. |
| [Rendu](doc/fr/rendering.md) | La bannière et le résumé, dessinés avec les recettes du kit. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester l'accès d'une application. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\AccessBundle\PhilAccessBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_access.yaml
phil_access:
    item_kinds: [page, document]
    groups:
        supporters: { entry_points: [purchase, invitation], duration: P1M }
```

```php
$items->register($page, MemberId::of($author->id), Visibility::Members);
$sharing->share($page, 'edit', MemberId::of($editor->id), new DateTimeImmutable('+7 days'));
```

```twig
{% if is_granted('access.edit', page) %}…{% endif %}
<twig:PhilAccess:ViewAsBanner />
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Doctrine DBAL 4.5 ou une version plus
récente ; SecurityBundle pour les voters, TwigBundle et Symfony UX Twig Component pour les fonctions et les
composants, EasyAdmin 5.8 ou une version plus récente pour son voter.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
