<!-- phil-dev source-sha256=58e96cc968bb97719efb46df503b3ea115a1993013bcb3ddc9d48c0808c0c26a -->
# Administration

Avec `admin.enabled`, les routes de `config/routes/admin.php` :

| Route | Page |
|---|---|
| `phil_terms_admin_list` | Les politiques, si chacune est requise et renouvelée, et leurs versions avec leurs moments, leur indicateur de changement important et leurs locales |
| `phil_terms_admin_publish` | Le formulaire qui publie la version suivante d'une politique |

Le formulaire demande le texte dans chacune des `locales` — une locale laissée vide est omise —, le format, le moment
de l'entrée en vigueur dans le `timezone` — vide ou passé, la version entre en vigueur à sa publication — et si le
changement est important, case non cochée par défaut. Une version publiée n'est jamais modifiée : une correction est la
version suivante.

Les moments sont gardés en UTC ; la liste les montre dans le `timezone`, que ses tableaux nomment dans leur légende.

Elle exige Twig, `security.authorization_checker` et `security.csrf.token_manager` ; le traducteur donne ses mots dans
la langue de l'utilisateur.

## Les recettes du kit

Les pages dessinent leurs parties avec les recettes du kit, du kit PHIL UX ou du kit Shadcn
([Installation](installation.md#installer-les-recettes-du-kit)) : une `Alert` pour une version publiée ou un refus, un
`Badge` pour dire si une politique est requise, un `Button` qui mène au formulaire, une `Table` des versions et, sur le
formulaire, un `Field` avec son `Field:Label` autour de chaque contrôle — `NativeSelect` pour le format, `Textarea` pour
chaque texte, `Input` pour le moment et la licence, `Checkbox` pour l'indicateur de changement important — et son
`Field:Description`. Seules les propriétés que les deux kits partagent sont passées, et aucun gabarit n'écrit un mot de sa
main.

## Rôle et jeton

Chaque page vérifie `admin.role` ; un utilisateur qui ne l'a pas se heurte au pare-feu, comme sur toute page refusée.
Le formulaire porte un jeton CSRF d'identifiant `phil_terms` ; un formulaire sans jeton valide est refusé avec un
`400`. Un refus — aucun texte, un document qui n'est pas en UJO, un moment illisible, un numéro pris entre-temps — est
affiché sur le formulaire avec un `422`, le brouillon étant conservé.

## Mise en page

Les pages étendent `admin.layout`, un gabarit qui définit les blocs `title` et `content`. La valeur par défaut est une
page HTML dépouillée qui demande aux robots de ne pas l'indexer ; une application la fait pointer vers un gabarit de
son interface d'administration.

## EasyAdmin

```php
use Phil\TermsBundle\Infrastructure\EasyAdmin\TermsAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Legal');
    yield from TermsAdmin::menuItems();
}
```
