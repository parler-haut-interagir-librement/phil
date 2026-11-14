<!-- phil-dev source-sha256=a1f039854fc14e9994f9965ee1db290c5a0cbfc2c7e9fb6a938c9bc64c5e4a2c -->
# Le filtre Doctrine

`Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter` est un `SQLFilter` de Doctrine ORM qui restreint au
locataire actif chaque entité marquée `#[TenantScoped]`, et laisse toutes les autres entités tranquilles.

## Marquer une entité

`Phil\TenancyBundle\Domain\Attribute\TenantScoped` est un attribut de classe qui nomme le champ contenant la référence du
locataire. Sa valeur par défaut est `tenant` ; un nom vide lance `InvalidValue`.

Par un champ associé qui contient la référence :

```php
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[TenantScoped(field: 'tenantRef')]
class PriceList
{
    #[ORM\Column(name: 'tenant_ref', length: 255)]
    private string $tenantRef;
}
```

Par une association sur une seule colonne vers l'entité de locataire de l'application :

```php
#[ORM\Entity]
#[TenantScoped] // field: 'tenant'
class Order
{
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'reference', nullable: false)]
    private Tenant $tenant;
}
```

Le filtre compare la **colonne** à la référence du locataire (`TenantId::$value`), toujours comme une chaîne entre
guillemets. Avec une association, la colonne de jointure doit donc contenir la référence du locataire elle-même :
référencez la colonne de référence de l'entité de locataire, ou faites de cette référence son identifiant.

## La collation de la colonne du locataire

`TenantId` compare les références octet par octet : `acme`, `ACME` et `acmé` sont trois locataires. La base de données
compare la colonne selon sa collation, et celle par défaut de MySQL 8 et de MariaDB (`utf8mb4_0900_ai_ci`,
`utf8mb4_general_ci`, …) ignore la casse et les accents : là, `tenant_ref = 'acme'` lit aussi les lignes de `ACME` et de
`acmé`, et un locataire voit celles d'un autre. Donnez à chaque colonne de locataire, et à la colonne de référence de
l'entité de locataire, une collation binaire :

```php
#[ORM\Column(name: 'tenant_ref', length: 255, options: ['collation' => 'utf8mb4_bin'])]
private string $tenantRef;
```

```php
// The tenant entity, whose reference the join columns hold.
#[ORM\Column(length: 255, unique: true, options: ['collation' => 'utf8mb4_bin'])]
private string $reference;
```

Sur PostgreSQL, la collation par défaut compare déjà la casse et les accents; une colonne dotée d'une collation ICU non
déterministe a besoin de `C` à la place. SQLite compare le texte octet par octet par défaut. Une application dont les
références sont par construction des *slugs* en minuscules ou des UUID n'est pas exposée, mais la collation ne coûte
rien et tient quand cela change.

## Ce que le filtre ajoute

| Entité | Locataire réglé sur le filtre | SQL ajouté |
|---|---|---|
| Non marquée, aucune sous-classe marquée | l'un ou l'autre | rien |
| Marquée | oui | `<alias>.<column> = '<reference>'`, la valeur mise entre guillemets par la connexion |
| Marquée | non | `1 = 0` : l'entité ne correspond à rien |
| Marquée, champ ni associé ni association sur une seule colonne | l'un ou l'autre | `MisconfiguredTenantScope` |
| Non marquée, mais une sous-classe est marquée | l'un ou l'autre | `MisconfiguredTenantScope` |

## Régler le locataire

Le bundle le règle : `DoctrineTenantActivation` suit `TenantContext`. Le locataire qu'entre l'écouteur de requêtes,
l'estampille d'un message ou `--tenant` est donc réglé sur le filtre de chaque gestionnaire d'entités où il est
activé, et quitter le locataire réactive le filtre à neuf, sans locataire, sur chaque gestionnaire d'entités qui le
connaît, y compris celui dont le filtre a été désactivé pour un travail entre locataires et laissé ainsi : la requête
suivante d'un serveur de longue durée ne commence jamais sans filtre. À la main, hors du contexte :

```php
$filter = $entityManager->getFilters()->getFilter(ActiveTenantFilter::NAME);
\assert($filter instanceof ActiveTenantFilter);
$filter->activate($resolution->tenant());
```

| Membre | Rôle |
|---|---|
| `const NAME = 'phil_tenancy'` | Le nom sous lequel enregistrer le filtre |
| `const PARAMETER = 'tenant'` | Le paramètre du filtre que règle `activate()` |
| `activate(TenantId $tenant): void` | Règle le locataire actif ; appelez-le une fois le locataire résolu, avant la première requête liée à un locataire |
| `addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string` | Appelé par Doctrine pour chaque entité que touche une requête |

Le bundle enregistre le filtre activé sur le gestionnaire d'entités par défaut (`doctrine_filter`). Avec plusieurs
gestionnaires, c'est l'application qui l'enregistre ; voir [installation.md](installation.md). Le filtre n'agit que
lorsqu'il est activé : enregistrez-le donc avec `enabled: true`.

## Choix de conception

- **Échec en mode fermé.** Sans locataire actif, une entité liée à un locataire ne correspond à rien : une requête qui
  a oublié de régler le locataire ne voit les lignes d'aucun locataire, plutôt que celles de tous.
- **Le travail entre locataires désactive le filtre explicitement**, là où un réviseur peut le voir : une commande de
  console, un rapport, une migration de données.

  ```php
  $filters = $entityManager->getFilters();
  $filters->suspend(ActiveTenantFilter::NAME);
  try {
      // work across tenants
  } finally {
      $filters->restore(ActiveTenantFilter::NAME);
  }
  ```

  Les méthodes `suspend()` et `restore()` de Doctrine gardent l'instance du filtre et son locataire. `disable()` suivi
  de `enable()` crée une nouvelle instance sans locataire, qui ne correspond à rien jusqu'à ce que `activate()` soit
  appelé de nouveau.
- **Une portée que le filtre ne peut pas appliquer est une erreur d'association**, levée à la première requête qui
  touche l'entité, même quand aucun locataire n'est réglé, plutôt qu'ignorée. Une entité que le filtre aurait sautée
  serait visible de tous les locataires.
- **L'attribut va sur la racine de l'héritage.** Doctrine donne au filtre la classe racine d'une hiérarchie. Un
  attribut déclaré seulement sur une sous-classe ne serait donc jamais lu, et les lignes de la sous-classe seraient
  montrées à tous les locataires. Le filtre refuse ce cas au lieu de l'ignorer. Un attribut sur la racine s'applique
  à toute la hiérarchie.
- **La valeur est mise entre guillemets par la connexion**, jamais concaténée dans le SQL.

## `MisconfiguredTenantScope`

`Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope` étend `LogicException` : c'est une erreur de
programmation, pas une condition d'exécution à attraper.

| Constructeur nommé | Message |
|---|---|
| `forField(string $entity, string $field)` | L'entité liée à un locataire nomme un champ qui n'est ni un champ associé ni une association sur une seule colonne |
| `onSubclass(string $root, string $subclass)` | L'entité est liée à un locataire, mais pas sa racine d'héritage ; la portée va sur la racine |

## Limites

- **DQL et l'ORM seulement.** Un filtre s'applique aux requêtes que construit l'ORM ; une requête par DBAL
  (`Connection`) ou en SQL natif n'est pas filtrée. Ajoutez-y la condition du locataire à la main.
- **Un locataire à la fois.** Le filtre compare avec une seule référence ; une requête sur plusieurs locataires le
  désactive.
- **Les écritures ne sont pas filtrées.** Le filtre restreint ce qui est lu. Réglez la référence du locataire sur les
  nouvelles entités à partir du locataire résolu, dans l'application.
