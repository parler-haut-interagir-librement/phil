<!-- phil-dev source-sha256=d40e4194a948d37178f814ac3e0766339fbf46a131f5ce98d541fe2cc65ec5f3 -->
# Administration

Les pages sont désactivées, sauf si `admin` est activé, avec `admin: true` ou l'une de ses clés et `enabled: true`.

## Les pages

| Route | Chemin sous le préfixe | Ce qu'elle montre |
|---|---|---|
| `phil_error_tracking_groups` | `/` | Les groupes, les derniers vus d'abord, par état (`?status=open`) et par page (`?page=2`) |
| `phil_error_tracking_group` | `/{fingerprint}` | Un groupe, son état et ses versions, et ses derniers événements avec leurs chaînes, leurs cadres, leur requête et leurs étiquettes |
| `phil_error_tracking_group_status` | `/{fingerprint}/{action}`, `POST` | Résout, ignore ou rouvre le groupe, puis l'affiche de nouveau |

Chaque page répond avec `Cache-Control: no-store`. Chaque valeur qui y figure est passée par l'assainisseur. Une chaîne se lit
de haut en bas : l'exception levée, puis chaque exception précédente marquée « Caused by », la cause première en dernier,
« Root cause ». Les cadres de chaque exception commencent par le plus récent ; chaque suite de cadres sous `vendor/` est
repliée dans un élément `<details>` qui dit combien elle en contient et s'ouvre sans script. Quand les alertes sont activées mais sans destination — l'état dégradé des
[Alertes](alerts.md) —, les deux pages le disent en haut, dans un paragraphe `role="status"` de
`@PhilErrorTracking/_alerts.html.twig`.

## Le rôle

Chaque page et chaque action demande `admin.role`, `ROLE_ADMIN` par défaut. Un utilisateur qui ne l'a pas rencontre le
pare-feu comme pour toute page refusée : un formulaire de connexion, ou une 403. Importez les routes sous un préfixe
que couvre le pare-feu.

## Les actions

Les formulaires envoient un jeton CSRF d'identifiant `phil_error_tracking` ; un jeton absent ou falsifié donne une
400. Résoudre garde la dernière version vue comme version de la résolution : un événement d'une autre version fait
régresser le groupe. Ignorer continue de compter, sans alertes. Rouvrir efface la résolution.

## La mise en page

Les pages étendent `admin.layout`, `@PhilErrorTracking/layout.html.twig` par défaut, une page simple sans ressources.
Une application donne sa propre mise en page, qui définit les blocs `title` et `main` — les noms que la page de contenu d'EasyAdmin emploie, pour que les mêmes pages
remplissent une interface d'administration :

```yaml
phil_error_tracking:
    admin:
        layout: admin/layout.html.twig
```

Dans une interface d'administration, voir plus bas : la mise en page est celle d'EasyAdmin, quoi que dise
`admin.layout`.

Les gabarits eux-mêmes, `@PhilErrorTracking/groups.html.twig`, `@PhilErrorTracking/group.html.twig` et
`@PhilErrorTracking/_alerts.html.twig`, peuvent être remplacés sous `templates/bundles/PhilErrorTrackingBundle/`.

## Dans une interface d'administration EasyAdmin

Avec EasyAdminBundle enregistré, le bundle greffe les mêmes pages sur chaque tableau de bord par `#[AdminRoute]`, avec
le même rôle, les mêmes refus et les mêmes formulaires. EasyAdmin nomme leurs routes d'après celles du tableau de bord :

| Route sous le tableau de bord `admin` | Chemin sous celui du tableau de bord | Même page que |
|---|---|---|
| `admin_errors_groups` | `/errors` | `phil_error_tracking_groups` |
| `admin_errors_group` | `/errors/{fingerprint}` | `phil_error_tracking_group` |
| `admin_errors_group_status` | `/errors/{fingerprint}/{action}`, `POST` | `phil_error_tracking_group_status` |

Sur ces routes, les pages sont rendues dans `@PhilErrorTracking/easyadmin/layout.html.twig`, qui étend la page de
contenu d'EasyAdmin : le titre de la page devient le titre du contenu, son corps le bloc `main`, dans le menu et le
thème du tableau de bord. Leurs liens et la redirection après un formulaire restent sur les routes du même tableau de
bord. Importer `config/routes.php` devient alors facultatif ; les alertes notifiées ne renvoient à un groupe que si les
routes du bundle existent.

Le menu du tableau de bord renvoie à la liste :

```php
use Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin\ErrorTrackingAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield ErrorTrackingAdmin::menuItem('admin');
}
```

`ErrorTrackingAdmin::menuItem()` prend le nom de la route du tableau de bord, un libellé (`Errors`) et une icône
(`fa fa-bug`). Couvrez le chemin du tableau de bord par le pare-feu, comme pour toute page de l'interface
d'administration.
