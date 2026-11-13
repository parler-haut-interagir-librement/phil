<!-- phil-dev source-sha256=56f200f6c0eeb5d0083091c492be7bc0c7f63255c1e43d4a9d033d6d3b73f4f9 -->
# Vérifier l'accès

Chaque vérification est faite pour le sujet de la requête : le membre comme qui un administrateur voit le site, sinon
le membre connecté, sinon un visiteur, `visitor:anonymous`, que seul ce qui est donné à tous les visiteurs atteint.

## Voters

`AccessVoter` répond à `access.<level>` sur une `ResourceReference` ou un `AccessibleItem` :

```php
$this->denyAccessUnlessGranted('access.edit', $page);
```

```twig
{% if is_granted('access.manage', page) %}…{% endif %}
```

```php
#[IsGranted('access.view', subject: 'page')]
public function show(Page $page): Response
```

Une entité devient un `AccessibleItem` en nommant la référence sur laquelle ses tuples sont écrits :

```php
final class Page implements AccessibleItem
{
    public function accessReference(): ResourceReference
    {
        return ResourceReference::of('page', (string) $this->id);
    }
}
```

`access.view_as` est accordé à un administrateur de l'espace. Chaque vote porte sa raison : la relation donnée, ou non.
Une vérification qui dépasse le budget est refusée et journalisée comme avertissement du canal `phil_access`.

## Dans les gabarits

| Fonction | Donne |
|---|---|
| `access_can('edit', page)` | Si le sujet de la requête peut agir à ce niveau ; l'élément est une `ResourceReference`, un `AccessibleItem` ou son texte, `page:42`. |
| `access_visibility(page)` | `everyone`, `members`, `admins`, `owner`, ou null avant l'enregistrement. |
| `access_viewing_as()` | L'identifiant du membre dont on voit le site, ou null. |

## Dans les services

`Application\ItemAccess` :

```php
$access->can('edit', $page);                                     // the request's subject
$access->allows(Subject::of($member->reference()), 'view', $page); // another subject
$access->explain('view', $page)?->lines();                       // why
$access->visible('page', limit: 100)->ids();                      // what it views, for WHERE id IN (:ids)
```

`visible()` liste les éléments d'un type sur lesquels le sujet peut agir à un niveau — la consultation par défaut —,
ordonnés par identifiant et limités ; `complete` est faux quand la liste a été coupée. Une longue liste d'éléments est
paginée par la requête de l'application sur ces identifiants, ou par un type plus étroit.

## Membres

Un membre est un `MemberId` : de 1 à 128 lettres, chiffres, `_`, `.` ou `-`, jamais avec `@`. Avec SecurityBundle, le
bundle le lit dans l'identifiant d'utilisateur du jeton, et refuse un identifiant qui est une adresse courriel : un tel
site nomme dans `phil_access.current_member` un service qui implémente `Application\Contract\CurrentMember` et retourne
l'identifiant stable de l'utilisateur.
