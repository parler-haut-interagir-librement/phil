<!-- phil-dev source-sha256=8318dc8878dd5db7534c331c2a5400226f7d806545681cd5d732f6f4dbaef91f -->
# Pages

## Les routes

| Route | Chemin | Page | Qui |
|---|---|---|---|
| `phil_library_catalogue` | `/` | La recherche et une page de titres | Tout le monde |
| `phil_library_title` | `/titles/{id}` | La fiche d'un titre et ses exemplaires | Tout le monde |
| `phil_library_my_loans` | `/my-loans` | Les prêts du membre | Le membre que nomme le résolveur |
| `phil_library_desk` | `/desk` | Prêter un exemplaire, en reprendre un | `roles.desk` |
| `phil_library_admin_title_new` | `/admin/titles/new` | Un nouveau titre | `roles.admin` |
| `phil_library_admin_title_edit` | `/admin/titles/{id}` | La fiche d'un titre | `roles.admin` |
| `phil_library_admin_copy_new` | `/admin/titles/{id}/copies/new` | Un nouvel exemplaire d'un titre | `roles.admin` |
| `phil_library_admin_overdue` | `/admin/overdue` | Les prêts en retard, avec leurs membres | `roles.admin` |

Les chemins sont sous le préfixe où l'application les monte. Une page du personnel demande son rôle au vérificateur
d'autorisation de l'application ; sans sécurité, elle est refusée à tout le monde. La page du membre interroge le
`MemberResolver` de l'application, nommé par `member_resolver` : une requête pour laquelle il ne nomme personne est
refusée, et sans résolveur la page est introuvable. Un titre qui n'existe pas est introuvable. Les pages sont envoyées
avec `private, no-cache`.

## Sans JavaScript

La recherche est un formulaire envoyé par GET : une page de résultats est un lien, les pages se suivent par des liens
`rel="prev"` et `rel="next"`, et rien n'a besoin d'un script. Elle ne porte aucun jeton CSRF, puisqu'elle ne change
rien. Le comptoir et l'administration sont des formulaires envoyés par POST avec le jeton CSRF de Symfony ; un refus est
dit sur le formulaire, dans la langue du lecteur, et répondu par 422. Un titre enregistré est suivi d'un
`303 See Other` vers sa page.

## Les recettes du kit

Les gabarits dessinent leurs éléments avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit ; rien n'est rendu depuis `vendor/` :

| Gabarit | Recettes |
|---|---|
| `catalogue.html.twig` | `Button` pour chercher et paginer ; `Table` avec ses parties pour les titres ; `Badge` pour les exemplaires sur l'étagère |
| `title.html.twig` | `Card` avec `Card:Header`, `Card:Title`, `Card:Description` et `Card:Content` pour la fiche ; `Table` pour les exemplaires |
| `_availability.html.twig` | `Badge` : sur l'étagère, sorti jusqu'à une date, en retard en rouge, retiré |
| `loans.html.twig`, `admin/overdue.html.twig`, `_loans.html.twig` | `Table` pour les prêts ; `Badge` pour l'état de chacun |
| `desk.html.twig` | `Alert` avec `Alert:Description` pour le prêt consenti ou retourné ; `Button` pour envoyer chaque formulaire |
| `admin/title_form.html.twig`, `admin/copy_form.html.twig` | `Button` pour enregistrer et pour ajouter un exemplaire |

Ces recettes ont les noms, les propriétés et les blocs du kit Shadcn du Toolkit, de sorte qu'un site sur Tailwind les
installe plutôt depuis le kit Shadcn. Elles se trouvent sous le sous-répertoire des composants de la configuration de
`ux_toolkit`, comme les nomme `phil_library_kit('Table:Row')` ; là où le Toolkit n'est pas activé, comme c'est souvent le
cas en production, répétez-le dans `pages.kit_subdirectory`. La mise en page du bundle est une page HTML nue ;
`pages.layout` nomme celle de l'application, qui définit `title` et `content` et charge la feuille de style de
phil/ux-css.

## Gabarits

| Gabarit | Contexte |
|---|---|
| `@PhilLibrary/catalogue.html.twig` | `form`, `result`, `query`, `layout` |
| `@PhilLibrary/title.html.twig` | `title`, `record`, `copies` (chaque `copy` et le prêt `out` qui la retient maintenant), `now`, `layout` |
| `@PhilLibrary/loans.html.twig` | `loans`, `now`, `layout` |
| `@PhilLibrary/desk.html.twig` | `lend`, `return`, `done` (le prêt consenti ou retourné par la requête), `layout` |
| `@PhilLibrary/admin/title_form.html.twig` | `form`, `id` (`null` pour un nouveau titre), `layout` |
| `@PhilLibrary/admin/copy_form.html.twig` | `form`, `title`, `layout` |
| `@PhilLibrary/admin/overdue.html.twig` | `loans`, `now`, `layout` |

Une application remplace n'importe lequel sous `templates/bundles/PhilLibraryBundle/`. Les textes sont dans le domaine
`phil_library`, en anglais et en français ; les messages des contraintes de formulaire, dans `validators`.
`phil_library_timezone()` donne le fuseau de la bibliothèque, dans lequel les gabarits montrent les dates conservées en
UTC.

## Une interface d'administration EasyAdmin

L'administration garde ses propres routes, ses formulaires et sa mise en page ; un tableau de bord EasyAdmin y renvoie :

```php
use Phil\LibraryBundle\Infrastructure\EasyAdmin\LibraryAdmin;

public function configureMenuItems(): iterable
{
    yield from LibraryAdmin::menuItems();    // the desk, the catalogue, a new title, the loans overdue
}
```

Faites pointer `pages.layout` vers un gabarit de l'interface d'administration pour que les pages lui ressemblent.
