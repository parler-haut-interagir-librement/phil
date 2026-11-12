<!-- phil-dev source-sha256=7a71df845e4f2fd8a88b7615db3d2008801ba99fa3d10c60d007413050c22fcb -->
# Slug

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Sluggable` | interface | `slug()`, `slugSource()`, `sluggerName()`, `slugScope()`, `assignSlug()` |
| `SluggableEntity` | trait | La colonne `slug`, 255 caractères, avec un index unique ; sans portée |
| `ScopedSluggableEntity` | trait | La colonne `slug`, 255 caractères, sans index ; l'entité déclare sa portée et son index |
| `SlugListener` | écouteur : `onFlush` | Fait le slug avec `phil/slug` et le garde unique |
| `Exception\SlugUnavailable` | exception | Chaque forme numérotée est prise |
| `Exception\InvalidScope` | exception | Une portée que l'écouteur ne sait pas comparer |

```php
#[ORM\Entity]
class Article implements Sluggable
{
    use SluggableEntity;

    public function slugSource(): string { return $this->title; }
}
```

Le texte devient un slug par les `Sluggers` de `phil/slug` : sa translittération, sa longueur et ses générateurs nommés
s'y trouvent tous, et `sluggerName()` en choisit un par son nom, celui par défaut quand il rend `null`.

## Quand un slug est fait

| Vidage | Slug |
|---|---|
| une entité insérée sans slug | fait à partir de `slugSource()` |
| une entité insérée avec un slug | gardé, une fois unique |
| une entité modifiée, son slug intact | laissé en paix, quel que soit son nouveau titre |
| une entité modifiée avec un nouveau slug | gardé, une fois unique |
| une entité après `assignSlug(null)` | fait de nouveau ; il peut sortir identique |
| une entité passée dans une autre portée | gardé s'il y est libre, numéroté sinon |

Une adresse qui change à chaque modification d'un titre est un lien brisé : un slug reste jusqu'à ce que l'application
demande autre chose.

## Unicité

Un slug déjà pris reçoit un numéro : `about`, `about-2`, `about-3`, chacun coupé à la taille de la colonne, jusqu'à
`SlugListener::MAX_ATTEMPTS`, 100, après quoi `SlugUnavailable` demande un autre titre. Trouver la première forme libre
est une seule requête `IN` sur la table elle-même : une ligne supprimée en douceur ou désactivée garde son slug, comme
le fait son entrée d'index. Les lignes du même vidage comptent aussi. Une sous-classe compte la table qui porte la
colonne.

La requête ne voit pas l'insertion d'une autre connexion qui n'a pas encore été validée. L'index unique de la colonne
tient alors, et le vidage lève `UniqueConstraintViolationException`, que l'application relance. Une entité qui
implémente `Sluggable` à la main déclare elle-même cet index, sur un champ nommé `slug`.

Un texte dont on ne peut rien tirer pour faire un slug lève l'`InvalidSlug` de `phil/slug`.

## Portée

Un slug peut être unique dans une portée plutôt que dans toute la table : un `about` par locataire, par locale, ou sous
chaque parent. `slugScope()` nomme les champs de la portée, chacun une colonne de l'enregistrement, colonne
d'énumération comprise, ou une relation vers un seul côté qu'il tient par une colonne ; `SluggableEntity` donne `[]`.
Une valeur nulle est une portée à elle seule, comparée par `IS NULL`.

```php
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['tenant_id', 'locale', 'slug'])]
class Page implements Sluggable
{
    use ScopedSluggableEntity;

    public function slugSource(): string { return $this->title; }
    public function slugScope(): array { return ['tenant', 'locale']; }
}
```

La requête qui trouve la première forme libre ajoute les valeurs de la portée à sa condition ; les lignes du même vidage
comptent dans leur propre portée seulement, et une portée dont la relation pointe vers un enregistrement du même vidage,
pas encore enregistré, ne contient encore rien. L'index unique sur les colonnes de la portée et le slug tient contre les
insertions simultanées, comme le fait l'index de la colonne sans portée. La plupart des plateformes laissent deux lignes
partager un slug quand une colonne de l'index est nulle : donnez à la portée des colonnes non nulles, ou un index
partiel de la plateforme, là où cela compte.

Le slug d'une `Translation` est unique dans une portée qui nomme sa locale, sinon `SlugListener` lève `InvalidScope` :
voir [Traduction](translation.md#slugs).

Une relation vers plusieurs, une relation tenue par plusieurs colonnes, ou une relation vers un enregistrement dont
l'identifiant est lui-même une relation lève `InvalidScope` au vidage qui en a besoin.
