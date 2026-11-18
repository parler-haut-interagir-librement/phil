<!-- phil-dev source-sha256=b28790b2d41a7ee67d293233c7fbd5df1944088586291adca8079382b4a8306c -->
# Stockage Doctrine

Avec DoctrineBundle, le bundle enregistre les racines, les lignes et les routes par l'ORM. L'application écrit **deux
entités par genre de contenu** et ne mappe ni la dimension ni les capacités : le bundle ajoute ce mappage quand
Doctrine charge les classes.

## Les deux entités

```php
namespace App\Content;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\{BodyCapable, BodyTrait, ExcerptCapable, ExcerptTrait,
    RoutableCapable, RoutableTrait, SeoCapable, SeoTrait, TemplateCapable, TemplateTrait,
    WorkflowCapable, WorkflowTrait};
use Phil\ContentBundle\Domain\Content\{AbstractContentRoot, AbstractDimensionContent};

#[ORM\Entity]
#[ORM\Table(name: 'news')]
class News extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: NewsIdType::NAME)]
    private NewsId $id;

    public function __construct()
    {
        $this->id = NewsId::generate();
    }

    public static function dimensionContentClass(): string
    {
        return NewsDimension::class;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'news_dimension')]
class NewsDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable,
    SeoCapable, ExcerptCapable, RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use RoutableTrait;
    use SeoTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
```

```yaml
phil_content:
    roots:
        - App\Content\News
```

`NewsId` est ici un `Identifier` de `phil/ddd-bundle` avec son `IdentifierType` ; tout type d'identifiant fonctionne,
voir [Références](#références-et-identifiants).

### `AbstractContentRoot`

Une superclasse mappée qui ne mappe rien. L'entité racine :

- **déclare son propre identifiant, dans une colonne nommée `id`**, à laquelle renvoie la colonne de jointure
  `root_id` de la ligne. Son type est celui de l'application : un entier généré par la base de données, une chaîne, ou
  un objet-valeur avec un type DBAL ;
- implémente `public static function dimensionContentClass(): string`, la classe de ses lignes. Une classe de ligne
  appartient à une seule classe racine ;
- peut redéfinir `public static function resourceKey(): string`, le nom de son genre de contenu dans les routes, les
  liens et les commandes. Par défaut, c'est le nom court de la classe en snake case (`News` donne `news`, `StaticPage`
  donne `static_page`) ; redéfinissez-le pour garder la clé quand la classe est renommée. Ce doit être une lettre
  minuscule suivie d'au plus 63 lettres minuscules, chiffres ou traits de soulignement ;
- hérite de `createDimensionContent(DimensionKey)`, qui construit une ligne vide de sa classe de ligne, rattachée à
  elle.

La racine ne mappe aucune collection de ses lignes, et peut contenir ses propres champs partagés.

### `AbstractDimensionContent`

Une superclasse mappée. Elle mappe :

| Champ | Colonne | Type |
|---|---|---|
| `id` | `id` | `phil_content_dimension_content_id` : un UUIDv7 généré à la construction de la ligne ; une colonne `uuid` native là où la plateforme en a une, `BINARY(16)` ailleurs |
| `locale` | `locale` | `phil_content_locale`, `VARCHAR(12)`, `NULL` pour la ligne non localisée |
| `localeKey` | `locale_key` | `VARCHAR(12)` : la locale, ou `*` pour la ligne non localisée |
| `stage` | `stage` | `phil_content_stage`, `VARCHAR(8)` |
| `version` | `version` | `phil_content_version`, `INT` |

**Son constructeur est final** et prend la racine et une `DimensionKey` : `AbstractContentRoot::createDimensionContent()`
construit les lignes par lui, avec ces deux arguments seulement. Une ligne reçoit ses valeurs par ses capacités.
Méthodes publiques : `id()`, `localeKey()`, `root()`, `dimension()` ; la constante `UNLOCALIZED_KEY` vaut `'*'`.

**L'unicité de la ligne non localisée est portable.** Aucune base de données ne compte deux `NULL` comme un doublon
dans un index unique — ni MySQL, ni PostgreSQL par défaut, ni SQLite. Un index unique sur `locale` laisserait donc
coexister deux lignes non localisées d'une même étape et d'une même version. La contrainte d'unicité couvre donc
`locale_key`, qui contient la sentinelle `*` à la place de `NULL` ; une `Locale` ne contient jamais `*`. Les deux
colonnes sont écrites une seule fois, par le constructeur, à partir d'une seule clé : elles ne peuvent donc pas
diverger. Une colonne générée éviterait la seconde colonne, au prix d'une définition brute qui diffère selon la
plateforme et que la comparaison de schémas lit comme un changement à chaque exécution.

## L'écouteur de mappage

`DimensionContentMappingListener` écoute `loadClassMetadata`. Pour chaque classe mappée :

1. **chaque capacité que la classe implémente reçoit ses colonnes**, à partir du `CapabilityMapping` étiqueté
   `phil_content.capability_mapping` pour cette interface ([capabilities.md](capabilities.md)) ;
2. **chaque sous-classe concrète de `AbstractDimensionContent`** reçoit :
   - une association plusieurs-à-un `root` vers la racine déclarée qui nomme la classe, sur une colonne de jointure
     `root_id` qui ne peut pas être nulle et qui est supprimée avec sa racine (`ON DELETE CASCADE`), renvoyant à
     l'`id` de la racine ;
   - la contrainte d'unicité `(root_id, stage, version, locale_key)`, sans nom pour que Doctrine en déduise un à
     partir de la table et des colonnes, ce qui la garde unique dans un schéma PostgreSQL.

L'ordre des colonnes de la contrainte sert les recherches du dépôt : une ligne par sa clé entière, les lignes d'une
étape et d'une version, et la plus haute version d'une étape lisent chacune un préfixe de ce seul index.

Règles que l'écouteur respecte :

- **un champ que la classe mappe elle-même est laissé tel qu'elle le mappe**, et les autres sont quand même ajoutés ;
- **une classe qui mappe `root` elle-même garde son association**, et la contrainte d'unicité nomme sa colonne de
  jointure au lieu de `root_id` ; elle doit avoir une seule colonne de jointure, sinon `InvalidContentMapping` est
  levée ;
- une classe de ligne qu'aucune racine déclarée ne nomme est refusée (`InvalidContentMapping`) ;
- un champ de capacité est mappé sur la première classe mappée qui contient la propriété : une superclasse mappée qui
  utilise le trait le mappe pour toutes ses sous-classes ; une superclasse mappée qui implémente l'interface sans la
  propriété est sautée ; **une entité qui implémente l'interface sans la propriété est refusée**.

## Les types DBAL

Enregistrés par le bundle dans la configuration `doctrine.dbal.types` :

| Nom | Classe | Colonne | Valeur PHP |
|---|---|---|---|
| `phil_content_locale` | `LocaleType` | `VARCHAR` de la longueur mappée, 12 par défaut | `Locale` |
| `phil_content_stage` | `StageType` | `VARCHAR` de la longueur mappée, 8 par défaut | `Stage` (sa chaîne sous-jacente est acceptée en entrée) |
| `phil_content_version` | `VersionType` | `INT` | `Version` (une chaîne numérique du pilote est lue) |
| `phil_content_dimension_content_id` | `DimensionContentIdType` | `uuid` natif, ou `BINARY(16)` | `DimensionContentId` |
| `phil_content_route_id` | `RouteIdType` | `uuid` natif, ou `BINARY(16)` | `RouteId` |

Ce sont de simples colonnes, pas des énumérations de base de données : la comparaison de migration d'une table mappée
ne trouve donc rien à changer. **Une valeur enregistrée qui n'est pas une locale, une étape ou une version valide est
refusée** en sortie comme erreur de conversion DBAL (`ValueNotConvertible`) : elle signale que la colonne contient ce
qu'aucun code n'a écrit, et une ligne lue malgré elle ne serait trouvée par aucune recherche.

## Les dépôts

| Port | Adaptateur Doctrine | Recherches |
|---|---|---|
| `Domain\Repository\DimensionContentRepository` | `DoctrineDimensionContentRepository` | `find()` une ligne par sa dimension ; `findAll()` chaque ligne d'une étape et d'une version, la ligne non localisée d'abord (la sentinelle `*` se trie avant toute lettre), puis par locale ; `latestVersion()` d'une étape ; `latestLocalizedVersion()` d'une locale dans une étape ; `findVersions()`, les lignes d'une locale dans une étape, la plus récente d'abord ; `add()`, `remove()` |
| `Domain\Repository\RouteRepository` | `DoctrineRouteRepository` | `findBySlug()` sur l'index unique `(resource_key, locale, slug)` ; `findCurrent()`, `findCurrentAmong()` (plusieurs contenus dans une seule liste `IN`) et `findAllOf()` sur l'index `(resource_key, resource_id, locale)` ; `add()`, `remove()` |
| `Domain\Repository\ContentRootRepository` | `DoctrineContentRootRepository` | `find()` par `ContentReference`, `findAll()` d'une clé de ressource, `referenceOf()` une racine, `add()`, `remove()` |
| `Domain\Repository\PublishedRowRepository` | `DoctrinePublishedRowRepository` | `findPublished()` : une page des lignes publiées d'un genre dans une locale, avec leurs racines et leurs lignes partagées, en deux requêtes |

L'adaptateur des lignes sert chaque racine de l'application : la classe de ligne est celle que nomme la racine. Il
enregistre seulement des lignes d'un `AbstractContentRoot` (`InvalidContentMapping` sinon). La ligne non localisée est
trouvée par sa sentinelle dans `locale_key`, jamais par `locale IS NULL`, pour lire le même index. Les plus hautes
versions sont lues par le persisteur d'entités (`findOneBy()` trié par version), qui lie la racine selon le type de son
propre identifiant.

Les lignes, routes et racines ajoutées ou retirées sont écrites quand la `ContentTransaction` vide vers la base.

## Références et identifiants

Chaque racine déclarée a une clé de ressource, et une **`ContentReference`** nomme une racine depuis l'extérieur du
stockage : sa clé de ressource et son identifiant en chaîne, `news:0192f3c4-…` ou `static_page:12`.

```php
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

$reference = ContentReference::fromString('news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70');
$reference = ContentReference::of('static_page', '12');
$reference->resourceKey;   // "static_page"
$reference->resourceId;    // "12"
$reference->toString();    // "static_page:12"
```

La clé de ressource est une lettre minuscule suivie d'au plus 63 lettres minuscules, chiffres ou traits de
soulignement. L'identifiant compte de 1 à 64 lettres, chiffres, traits d'union ou traits de soulignement, ce qui
contient un UUID, un ULID ou un entier. Toute autre forme lève `InvalidContent`.

`DoctrineContentRootRepository` et `DoctrinePublishedRowRepository` reconstruisent l'identifiant à partir de la chaîne
selon le type déclaré de la propriété d'identifiant de la racine (`RootIdentifiers`). Ainsi, une recherche lie ce
qu'attend le type DBAL de l'identifiant sur chaque plateforme :

| Type de la propriété | La chaîne est lue comme |
|---|---|
| `int` | une chaîne d'au plus 18 chiffres, convertie en entier ; toute autre forme ne trouve aucune racine |
| `string`, ou sans type | la chaîne telle quelle |
| une classe avec une méthode statique `fromString()` — un `Identifier` de `phil/ddd-bundle`, un `Uuid` de `symfony/uid` | construite par `fromString()` ; une chaîne qu'elle refuse par une `DomainError` ou une `InvalidArgumentException` ne trouve aucune racine |
| une autre classe | la chaîne telle quelle |

Dans l'autre sens, `referenceOf()` écrit l'identifiant sous forme de chaîne : un entier en décimal, une chaîne telle
quelle, un `Stringable` par `__toString()`. Un identifiant composite, un identifiant pas encore défini (une clé générée
par la base de données avant le vidage) ou un identifiant sans forme en chaîne lève `InvalidContent`.

Une référence dont aucune racine déclarée n'a la clé de ressource lève `InvalidContentMapping` depuis l'adaptateur :
une référence vers un genre inconnu est une erreur du code ou de la configuration de l'appelant, pas un état du
contenu.

## Copies de travail

`Domain\Draft\WorkingCopy` est une entité du bundle, associée par des attributs (`PhilContentBundleDraft`) à la table
`phil_content_working_copy` : l'enregistrement automatique d'un auteur ou un brouillon nommé d'un contenu dans une
locale ([administration.md](administration.md#copies-de-travail)).

| Colonne | Type | Contient |
|---|---|---|
| `id` | `phil_content_working_copy_id` | un UUIDv7 |
| `resource_key`, `resource_id` | chaîne de 64 | le contenu, indexé avec `locale` |
| `locale` | `phil_content_locale` | la locale du brouillon à côté duquel elle se trouve |
| `author` | chaîne de 255, nullable | l'auteur d'un enregistrement automatique ; le rédacteur qui a enregistré en dernier un brouillon nommé |
| `name` | chaîne de 100, nullable | le nom d'un brouillon nommé ; `null` pour un enregistrement automatique |
| `data` | `json` | les données soumises, 256 Kio de JSON au plus |
| `saved_at` | `datetime_immutable` | le moment de l'enregistrement, en UTC |
| `raw_html` | booléen, `false` par défaut | si le rédacteur qui l'a enregistrée en dernier avait la permission d'écrire du HTML brut ; l'appliquer écrit son corps avec cette permission |

`DoctrineWorkingCopyRepository` implémente son port, `Domain\Contract\WorkingCopyRepository` ; il n'écrit rien
lui-même, dans la transaction de l'appelant.

## La racine d'un genre unique

`SingleRoot` est une entité du bundle, associée avec les racines de contenu, sur sa propre table :

| Colonne | Type | Contient |
|---|---|---|
| `resource_key` | chaîne de 64, clé primaire | le genre unique (`phil_content.singles`) |
| `resource_id` | chaîne de 64 | l'identifiant de sa racine |

`ContentManager::create()` écrit la ligne d'un genre unique dans la transaction qui crée sa racine, une fois que la
racine a son identifiant, et `ContentManager::delete()` la retire avec la racine. Deux créations du genre simultanées
ne trouvent toutes deux aucune racine et en créent chacune une ; **la clé primaire refuse la seconde ligne**, et
`DoctrineSingleRootRepository`, l'adaptateur de `Domain\Contract\SingleRootRepository`, transforme le refus en la même
`InvalidContent` qu'une création qui trouve déjà la racine. La ligne est écrite par une instruction de la connexion,
aussitôt, non par l'unité de travail : le refus laisse le gestionnaire d'entités ouvert, et la transaction de la
création est annulée, racine comprise. Les racines des autres genres n'ont aucune ligne.

### Mise à niveau depuis la version 0.1

La table est nouvelle. Migrez le schéma :

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Les genres uniques créés avec la version 0.1 n'ont besoin d'aucune ligne : `ContentManager::create()` cherche encore
d'abord une racine existante et en refuse une seconde, et la ligne est écrite à la prochaine création du genre. Un genre
qui contient déjà deux racines les garde ; supprimez celle dont vous ne voulez pas avec `ContentManager::delete()`.

## Construire des racines

`ContentManager::create()` construit une nouvelle racine par le port `ContentRootFactory`. L'adaptateur Doctrine,
`ConstructorContentRootFactory`, appelle le constructeur de la racine **sans arguments** : la racine génère son propre
identifiant, ou le laisse à la base de données. Une racine dont le constructeur exige des arguments, ou n'est pas
public, est refusée par `InvalidContentMapping` ; faites de `ContentRootFactory` un alias vers votre propre fabrique
([extension-points.md](extension-points.md#contentrootfactory)).

## Transactions

`DoctrineContentTransaction` implémente `ContentTransaction` :

- `run(callable)` commence une transaction sur la connexion du gestionnaire d'entités, exécute l'opération, vide vers
  la base et valide. **Dans une transaction déjà ouverte** — celle d'une migration Doctrine, celle de l'appelant —,
  DBAL l'imbrique dans un point de sauvegarde ;
- `flush()` écrit ce qui est en attente dans la transaction en cours : une ligne retirée et une ligne de la même
  dimension ajoutée doivent atteindre la base de données dans cet ordre, ce qu'un seul vidage ne promet pas. Un index
  unique qui la refuse devient une `WriteConflict` (`Application\Exception`), dont l'exception précédente est celle de
  Doctrine : l'opération qui a vidé nomme la clé dans les mots du domaine. `ContentPublisher` écrit la route d'une
  publication par un vidage à elle, et une `WriteConflict` à cet endroit est une `SlugTaken`.

En cas d'échec, elle annule, **vide l'unité de travail** et relance l'exception :

- **le vidage atteint l'appelant.** Imbriqué dans une transaction de l'appelant, un échec détache aussi les entités de
  l'appelant ; elles sont rechargées avant d'être écrites ;
- **le gestionnaire d'entités reste ouvert seulement quand l'échec survient avant un vidage** : un refus du domaine, un
  slug déjà pris, une transition refusée. Un échec de la base de données pendant un vidage — un index unique qui
  refuse une publication concurrente, le même slug pris deux fois au même moment — le ferme, comme `doctrine/orm` le
  ferme chaque fois que son commit échoue. Un appelant qui attrape une telle erreur et écrit de nouveau réinitialise
  d'abord le gestionnaire d'entités (`ManagerRegistry::resetManager()`).

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une route, une racine, une copie de travail et une
ligne se trouvent par leurs clés telles qu'elles ont été écrites : le contenu `news:Doc-A` n'est jamais `news:doc-a`,
ses routes ne sont jamais listées pour l'autre, et la sauvegarde automatique de l'auteur `Ann` n'est jamais donnée à
`ann`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_content_route` | `resource_key`, `resource_id`, `slug`, `locale` |
| `phil_content_single_root` | `resource_key`, `resource_id` |
| `phil_content_working_copy` | `resource_key`, `resource_id`, `locale`, `author` |
| `la table de chaque classe de ligne` | `locale`, `locale_key`, `stage` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.2

Les tables d'une installation 0.2 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT resource_key, resource_id, COUNT(*) AS row_count FROM phil_content_route
GROUP BY resource_key, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.
