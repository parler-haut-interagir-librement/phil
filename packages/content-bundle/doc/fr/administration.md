<!-- phil-dev source-sha256=dc4288db76e7307852e46976df2611158b0ab8ab9228d63843bd3fa389d47446 -->
# Administration et copies de travail

L'administration modifie et publie les contenus depuis un tableau de bord. Ses écrans passent par les services
d'application du bundle — `ContentManager`, `WorkingCopies`, `PublicationSchedule` —, de sorte qu'ils valident,
refusent, répartissent les événements de page et écrivent exactement comme la console et PHP. Un tableau de bord
EasyAdmin y mène.

## L'activer

```yaml
# config/packages/phil_content.yaml
phil_content:
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/content_layout.html.twig   # a template of the back office, with "title" and "content" blocks
        timezone: America/Toronto

# config/routes/phil_content_admin.yaml
phil_content_admin:
    resource: '@PhilContentBundle/config/routes_admin.php'
    prefix: /admin/contents
```

Les routes se placent derrière le pare-feu de l'application ; chaque écran redemande `admin.role`. L'administration a
besoin de SecurityBundle avec la protection CSRF (`framework.csrf_protection`), et de `symfony/translation` pour ses
libellés, dans le domaine `phil_content`, en anglais et en français.

Dans le tableau de bord EasyAdmin :

```php
use Phil\ContentBundle\Infrastructure\EasyAdmin\ContentAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield from ContentAdmin::menuItems();
}
```

`ContentAdmin::menuItems()` a besoin de `easycorp/easyadmin-bundle` ; les écrans eux-mêmes n'en ont pas besoin.

## Les écrans

| Route | Chemin sous le préfixe | Fait |
|---|---|---|
| `phil_content_admin_list` | `/` | Chaque genre de `phil_content.roots`, ses contenus et, par locale, la place du brouillon, sa version et la version servie aux lecteurs |
| `phil_content_admin_create` | `/new/{resourceKey}` | Crée un contenu du genre dans une locale, avec son slug, puis ouvre son écran de modification |
| `phil_content_admin_edit` | `/{reference}/{locale}` | L'écran de modification d'un contenu dans une locale |
| `phil_content_admin_autosave` | `/{reference}/{locale}/autosave` | Garde le formulaire de modification comme enregistrement automatique de l'auteur ; répond 204, ou 422 avec la raison |

L'écran de modification montre les champs que donnent les capacités du brouillon — gabarit, slug, titre, résumé, titre
de référencement, description et URL canonique, le format du texte et le texte, et les trois indicateurs de
référencement — et ses boutons :

| Bouton | Fait |
|---|---|
| Enregistrer le brouillon | `ContentManager::edit()` ; l'enregistrement automatique de l'auteur est écarté |
| Publier | enregistre, puis `ContentManager::publish()`. Un corps que la conversion en UJO ne garde pas entier est refusé : l'écran liste ce qui serait perdu, et publie une fois « publier quand même » coché |
| Mettre hors ligne | `ContentManager::unpublish()` |
| Écarter les modifications | `ContentManager::removeDraft()` |
| Planifier, annuler ce qui est planifié | `PublicationSchedule::schedule()` et `cancel()`, à un moment de `admin.timezone` ; montrés seulement quand une planification est installée |
| Enregistrer comme brouillon nommé, l'utiliser, le supprimer | `WorkingCopies::saveNamed()`, `applyNamed()`, `deleteNamed()` |
| Les restaurer, les écarter | `WorkingCopies::applyAutosave()` et `discardAutosave()` sur l'enregistrement automatique de l'auteur |

- **Un refus montre sa raison** et garde ce qui a été saisi, avec la réponse 422 : un slug pris, une valeur refusée,
  une publication qui perd du contenu.
- **Chaque formulaire porte un jeton CSRF** d'identifiant `phil_content_admin` ; un formulaire qui en est dépourvu reçoit
  la réponse 400.
- **Chaque écran est servi `no-store`.**
- **L'auteur** est l'identifiant d'utilisateur du jeton de sécurité ; une requête sans utilisateur connecté est refusée.

## Copies de travail

Une copie de travail est un ensemble de données soumises gardé à côté du brouillon d'une locale, et écrit dans celui-ci
seulement quand un rédacteur l'applique. Elle est stockée dans la table `phil_content_working_copy`
([storage.md](storage.md#copies-de-travail)).

| Genre | Appartient à | Gardé |
|---|---|---|
| Enregistrement automatique | un auteur, lu par personne d'autre | pendant que l'auteur écrit, en remplaçant le précédent ; retiré une fois appliqué, ou quand l'auteur enregistre ou publie le brouillon |
| Brouillon nommé | le contenu, lu par chaque rédacteur | sous un nom de 1 à 100 caractères, remplacé quand on l'enregistre de nouveau sous le même nom ; reste une fois appliqué |

`Application\Editing\WorkingCopies` :

| Méthode | Fait |
|---|---|
| `autosave(ContentReference, $locale, string $author, array $data): WorkingCopy` | garde l'enregistrement automatique de l'auteur |
| `autosaved(ContentReference, $locale, string $author): ?WorkingCopy` | l'enregistrement automatique de l'auteur |
| `discardAutosave(ContentReference, $locale, string $author)` | le retire, s'il y en a un |
| `applyAutosave(ContentReference, $locale, string $author)` | l'écrit dans le brouillon par `ContentManager::edit()`, puis le retire |
| `saveNamed(ContentReference, $locale, string $name, string $author, array $data): WorkingCopy` | garde un brouillon nommé |
| `named(ContentReference, $locale): list<WorkingCopy>` | les brouillons nommés, par nom |
| `applyNamed(ContentReference, $locale, string $name)` | écrit un brouillon nommé dans le brouillon |
| `deleteNamed(ContentReference, $locale, string $name)` | le retire |

- Les données sont les données soumises du pipeline ([pipeline.md](pipeline.md#données-soumises)), 256 Kio de JSON au
  plus ; elles sont validées à l'application, comme n'importe quelle modification.
- Un contenu qui n'existe pas, ou une locale non servie, est refusé avant que rien ne soit gardé. Un enregistrement
  automatique ou un brouillon nommé absent donne une `ContentNotFound`.
- `ContentManager::delete()` retire avec le contenu ses copies de travail.

## Le contrôleur d'enregistrement automatique

Le formulaire de modification porte le contrôleur Stimulus `phil--content-bundle--autosave`, servi depuis `assets/` par
les conventions de Symfony UX (AssetMapper ou Webpack Encore). Une fois que la saisie s'arrête pendant `delay`
millisecondes — 2000 par défaut —, il envoie le formulaire, ses champs et son jeton CSRF, à la route d'enregistrement
automatique ; un enregistrement s'exécute à la fois, et une modification faite entre-temps est enregistrée après lui.
L'attribut `data-autosave-state` du formulaire dit `saving`, `saved` ou `failed`, pour un style ou une région vivante.
Rien n'est gardé dans le navigateur.
