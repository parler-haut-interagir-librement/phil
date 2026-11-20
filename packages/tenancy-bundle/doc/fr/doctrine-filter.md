<!-- phil-dev source-sha256=1369b3ff3109d0f54e3088eb8e4a05bc0c1fae036040335620475b47ed28b464 -->
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
| `tenant(): ?string` | La référence réglée sur ce filtre, `null` tant qu'il ne correspond à rien |
| `addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string` | Appelé par Doctrine pour chaque entité que touche une requête |

Le bundle enregistre le filtre activé sur le gestionnaire d'entités par défaut (`doctrine_filter`). Avec plusieurs
gestionnaires, c'est l'application qui l'enregistre ; voir [installation.md](installation.md). Le filtre n'agit que
lorsqu'il est activé : enregistrez-le donc avec `enabled: true`.

## La table d'identité

Doctrine répond à `find()`, à `getReference()` et à une association paresseuse depuis sa table d'identité, avant toute
requête : une entité déjà chargée est retournée telle quelle, et le filtre ne voit jamais l'appel. Dans une base de
données partagée, une entité chargée pendant qu'un locataire est actif serait retrouvée après le passage à un autre.
`DoctrineTenantActivation` ferme ce passage : quand le locataire d'un gestionnaire d'entités dont le filtre est activé
change — un autre locataire est entré, ou le locataire est sorti —, elle détache les entités liées à un locataire que le
gestionnaire détient.

```php
$order = $entityManager->find(Order::class, 7);                    // in the tenant "retail"

$context->within(TenantId::fromString('wholesale'), static fn (): ?Order => $entityManager->find(Order::class, 7));
// null: the order of "retail" is detached, and the filter of "wholesale" does not match its row

$order = $entityManager->find(Order::class, 7);                    // back in "retail": loaded again
```

| Changement | Entités liées à un locataire | Entités partagées |
|---|---|---|
| Le même locataire entre de nouveau | conservées | conservées |
| Un autre locataire entre | détachées | conservées |
| Le locataire sort | détachées | conservées |
| Aucun locataire ne reste, le filtre n'en portant aucun | conservées | conservées |

Une entité planifiée pour une insertion ou une suppression n'est jamais détachée, afin que son écriture ne soit pas
abandonnée en silence : la garde d'écriture ci-dessous refuse de la vider sous un autre locataire, et elle est écrite
dès que son propre locataire est de nouveau actif. Une modification d'une entité chargée n'est pas planifiée avant le
vidage : **videz avant de changer de locataire**, et rechargez une entité après `TenantContext::within()` plutôt que de
garder l'instance chargée auparavant.

## La garde d'écriture

Le filtre restreint les lectures. `TenantWriteGuard`, que le bundle enregistre sur `onFlush` tant que `doctrine_filter`
est activé, refuse un vidage qui insérerait, modifierait ou supprimerait une entité liée à un locataire autre que celui
réglé sur le filtre du gestionnaire d'entités qui vide : le vidage lance `CrossTenantWrite`, et rien n'est écrit.

| Unité de travail | Entité du locataire actif | Entité d'un autre locataire, ou d'aucun |
|---|---|---|
| Un locataire réglé sur le filtre activé | écrite | `CrossTenantWrite` |
| Aucun locataire sur le filtre : une migration, un jeu de données d'essai, une commande lancée sans `--tenant` | écrite | écrite |
| Le filtre désactivé ou suspendu : travail entre locataires | écrite | écrite |

Le locataire d'une entité se lit dans le champ que nomme son attribut : la référence que contient un champ associé, ou
l'identifiant de l'entité locataire que désigne une association sur une seule colonne (`EntityTenant::of()`). Les
entités partagées sont laissées tranquilles. La garde est la dernière ligne de défense dans une base de données
partagée sans sécurité au niveau des lignes ; l'application règle quand même la référence du locataire d'une nouvelle
entité à partir du locataire résolu.

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
- **Les écritures sont gardées, non filtrées.** Le filtre restreint ce qui est lu ; la garde d'écriture refuse un vidage
  qui écrit l'entité d'un autre locataire, mais les écritures par DBAL ou en SQL natif ne sont vues ni de l'un ni de
  l'autre. Réglez la référence du locataire sur les nouvelles entités à partir du locataire résolu, dans l'application,
  et utilisez la sécurité au niveau des lignes ([row-level-security.md](row-level-security.md)) pour une barrière sous
  l'ORM.
