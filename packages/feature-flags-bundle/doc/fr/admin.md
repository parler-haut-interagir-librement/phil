<!-- phil-dev source-sha256=4e5f93b98b0c4796dd05b87afbaec12e38104cc7992c7ed018906f3e3afbad74 -->
# Les écrans d'administration

Des écrans HTML rendus côté serveur pour lister, examiner, basculer, créer et modifier des indicateurs. Ils sont activés
par `admin`, qui exige `toggle` ([configuration.md](configuration.md#admin)) ; la création et la modification exigent
aussi un `definition_store` (le magasin en base de données en fournit un).

## Routes

Importez-les sous un préfixe ([installation.md](installation.md#les-routes-dadministration)) :

```yaml
phil_feature_flags_admin:
    resource: '@PhilFeatureFlagsBundle/config/routes/admin.php'
    prefix: /admin/flags
```

| Nom de route | Chemin | Méthodes | Action |
|---|---|---|---|
| `phil_feature_flags_admin_list` | `/` | GET | Chaque indicateur des fournisseurs énumérables, illisibles compris. |
| `phil_feature_flags_admin_create` | `/_new` | GET, POST | Le formulaire de création (404 sans magasin de définitions). |
| `phil_feature_flags_admin_show` | `/{name}` | GET | Un indicateur, avec sa bascule. |
| `phil_feature_flags_admin_toggle` | `/{name}/toggle` | POST | Bascule l'indicateur. |
| `phil_feature_flags_admin_edit` | `/{name}/edit` | GET, POST | Le formulaire de modification d'un indicateur du magasin de définitions. |

`{name}` doit respecter l'alphabet des noms d'indicateur (`[a-z0-9]+(?:[._-][a-z0-9]+)*`). Le formulaire de création se
trouve à `/_new`, ce qu'aucun nom d'indicateur ne peut être : chaque indicateur, `new` compris, a donc sa propre page.

## Écrans

- **Liste** : chaque indicateur avec son état effectif (`(stored)` lorsque le magasin d'états le contient), son genre,
  son ciblage (`everyone`, `N rules`, ou le `A/B by …` de l'expérience), son responsable, son expiration (marquée
  **expired**) et sa source (l'identifiant de service du fournisseur). Un lien « New flag » apparaît lorsque l'éditeur
  existe. À leur suite, chaque indicateur dont la définition stockée ne se lit plus (`FlagCatalog::unreadable()`) a sa
  propre rangée : son nom, **unreadable**, la raison et la source.
- **Indicateur illisible** : la page d'un tel indicateur, avec son nom, la raison que donne son fournisseur, la source,
  et un rappel qu'il est désactivé pour chaque contexte (`PARSE_ERROR`) ; ni bascule ni lien de modification. Répondue
  avec 200.
- **Indicateur** : la description, l'état (« as declared by » le fournisseur, ou « stored; declared active/inactive by »
  celui-ci), le genre, le responsable, l'expiration, chaque règle en mots (`tenant in tenant-a, tenant-b`,
  `20 % of each tenant`), l'expérience, et un formulaire pour activer ou désactiver avec une raison obligatoire. Un
  indicateur dont la source est le magasin de définitions a un lien « Edit the rules and metadata ». Après un
  changement, une ligne d'état indique que l'indicateur a été `toggled`, `created` ou `edited`.
- **Nouvel indicateur / Modification** : le nom (à la création seulement), la description, le responsable, le genre (une
  liste déroulante), l'expiration (un champ de date), les règles et l'expérience sous forme d'objet JSON, et une raison
  obligatoire. Le JSON n'accepte que les clés `rules` et `experiment` ; l'état ne peut pas être fixé ici : **un nouvel
  indicateur commence inactif** (voyez l'exception dans
  [toggles-and-edits.md](toggles-and-edits.md#créer-un-indicateur)) et son activation est une bascule. Le formulaire
  de modification porte la révision à laquelle il a été lu.

Après un changement réussi, le navigateur est redirigé vers la page de l'indicateur avec **303 See Other** et un
paramètre de requête `done`, `toggled`, `created` ou `edited`, que la page signale dans une ligne d'état. Toute autre
valeur de `done` est ignorée : un lien fabriqué ne peut pas placer ses propres mots sur l'écran d'administration.

## Réponses

| Situation | Statut |
|---|---|
| `ActorProvider::current()` retourne `null` | 403, sur chaque écran |
| Un formulaire envoyé à partir d'un autre site | 403 |
| Indicateur inconnu, nom invalide, création ou modification sans l'éditeur, modification d'un indicateur absent du magasin de définitions, bascule ou modification d'une définition stockée illisible | 404 |
| Raison vide, JSON ou définition mal formés (`InvalidValue`, `JsonException`) | 422, le formulaire réaffiché avec le message |
| Refus de l'autorisateur (`ToggleRefused`) | 403, la page ou le formulaire réaffiché avec le message |
| Changement concurrent (`ToggleConflict`, `EditConflict`), nom déjà déclaré | 409, la page ou le formulaire réaffiché avec le message |

## Garde de même origine

Chaque POST (bascule, création, modification) passe par `SameOriginGuard::assert()`, avec les en-têtes de requête
standard et sans jeton :

1. lorsque le navigateur envoie `Sec-Fetch-Site`, cet en-tête doit valoir `same-origin` ;
2. sinon, l'en-tête `Origin` doit être égal au schéma et à l'hôte de la requête (`Request::getSchemeAndHttpHost()`) ;
3. une requête qui ne porte aucun de ces en-têtes est refusée.

Derrière un mandataire inverse, configurez les mandataires de confiance de Symfony pour que le schéma et l'hôte que voit
l'application soient les valeurs publiques ; sinon, la comparaison de `Origin` échoue. Les écrans GET ne sont pas
gardés ; ils ne changent rien.

## L'opérateur et le pare-feu

Le bundle n'authentifie pas. L'application :

- place le préfixe derrière son pare-feu (une règle `access_control`, un rôle) ;
- implémente `ActorProvider::current(): ?string`, habituellement à partir du jeton de sécurité, qui retourne
  l'identifiant opaque inscrit dans les lignes d'audit, ou `null` lorsque personne n'est authentifié ;
- décide de chaque changement dans son `ToggleAuthorizer`.

```php
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class OperatorProvider implements ActorProvider
{
    public function __construct(private Security $security) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
```

## Gabarits et mise en page

Les gabarits se trouvent dans le `templates/admin/` du bundle et sont rendus sous le nom
`@PhilFeatureFlags/admin/<name>.html.twig` :

| Gabarit | Variables |
|---|---|
| `list.html.twig` | `entries` (`list<CatalogEntry>`), `unreadable` (`list<UnreadableEntry>`), `can_create` (bool) |
| `show.html.twig` | `entry` (`CatalogEntry`), `effective` (`FlagDefinition`), `expired` (bool), `editable` (bool), `done` (string) |
| `unreadable.html.twig` | `entry` (`UnreadableEntry`) |
| `form.html.twig` | `form` (table de `name`, `definition`, `description`, `owner`, `kind`, `expires_on`, toutes des chaînes), `revision` (`?int`, `null` à la création) |

Chaque gabarit reçoit aussi `layout` (la mise en page configurée), `now` (l'heure de l'horloge) et `error` (`?string`).

Chaque gabarit fait `{% extends layout %}` et remplit deux blocs, `title` et `content`. La mise en page par défaut,
`@PhilFeatureFlags/admin/layout.html.twig`, est une page HTML dépouillée. Pour rendre les écrans à l'intérieur de
l'interface d'administration de l'application, faites pointer `admin.layout` vers un gabarit de l'application qui
définit ces deux blocs :

```yaml
phil_feature_flags:
    admin:
        layout: 'admin/flags_layout.html.twig'
```

```twig
{# templates/admin/flags_layout.html.twig #}
{% extends 'admin/base.html.twig' %}
{% block title %}{% endblock %}
{% block body %}<div class="flags">{% block content %}{% endblock %}</div>{% endblock %}
```

Pour changer un écran lui-même, remplacez-le de la façon habituelle de Symfony, sous
`templates/bundles/PhilFeatureFlagsBundle/admin/<name>.html.twig`, en conservant les noms des champs du formulaire
(`active`, `reason`, `name`, `description`, `owner`, `kind`, `expires_on`, `definition`, `revision`) et les noms des
routes.

Les formulaires ne portent aucun jeton CSRF : la garde de même origine le remplace.
