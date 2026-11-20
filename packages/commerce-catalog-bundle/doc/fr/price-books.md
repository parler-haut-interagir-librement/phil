<!-- phil-dev source-sha256=7c6846245d4270977ea2a57f1a3cca80ba994cb9df1bec359e74eaef25250467 -->
# Grilles de prix

Une grille de prix donne des prix unitaires autres que les prix de base, selon à qui et quand elle s'applique : un
groupe de clients, un canal — un site, un point de vente —, une période, et des paliers de quantité par *SKU*.

```php
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;

$blackFriday = PriceBook::create('black-friday', 'Black Friday', Currency::of('CAD'), channel: 'web',
    startsAt: new DateTimeImmutable('2026-11-27 00:00 America/Toronto'), endsAt: new DateTimeImmutable('2026-12-01 00:00 America/Toronto'));

$wholesale = PriceBook::create('wholesale', 'Wholesale', Currency::of('CAD'), customerGroup: 'wholesale', after: ['black-friday']);
$wholesale->setPrice(PriceTier::of('DICE-D20', 1, Money::of(399, Currency::of('CAD'))));
$wholesale->setPrice(PriceTier::of('DICE-D20', 50, Money::of(349, Currency::of('CAD'))));
```

## L'ordre des grilles

Une grille nomme, dans sa liste `after`, les grilles après lesquelles elle est essayée : leurs prix l'emportent sur les
siens partout où les deux s'appliquent. `PriceBookOrder::sort()` trie les grilles par code, puis place chacune après
les grilles que nomme sa liste, avec `BeforeAfterSorter`, le tri que Symfony applique aux services étiquetés. Aucun
numéro ne classe une grille : en ajouter une entre deux autres, c'est les nommer, pas renuméroter les autres.

| Cas | Ordre |
|---|---|
| Aucune liste | Par code, dans l'ordre des octets |
| `wholesale` après `black-friday` | `black-friday` est essayée en premier, où que tombent leurs codes |
| Un nom qui n'est aucune grille active | Ignoré : une grille retirée ou supprimée laisse les autres là où leurs autres noms les placent |
| Des listes qui bouclent — `a` après `b`, `b` après `a` | Mises de côté : les grilles sont essayées par code |

## Le prix que prend une ligne

`PriceBookPrices` répond au noyau pour les références de type `product`, avec la quantité totale de la ligne :

1. les grilles non retirées, dans l'ordre de leurs listes `after` ;
2. celles dans la devise du produit qui s'appliquent : le groupe de clients de l'acheteur et le canal — tous deux tirés
   du `PricingContext` du noyau —, et le moment, début compris et fin exclue ; une condition laissée vide s'applique à
   tous, partout, toujours ;
3. la première qui donne un prix au *SKU* pour la quantité l'emporte, avec le palier de la plus haute quantité
   atteinte ;
4. aucune grille : le prix de base du produit.

| Membre de `PriceBook` | Règle |
|---|---|
| `code` | Jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-` |
| `name`, `currency` | Un nom ; la devise de ses prix |
| `after()`, `placeAfter(list)` | Les grilles après lesquelles elle est essayée : jusqu'à 1 000 codes, jamais le sien ; un nom répété compte une fois |
| `customerGroup`, `channel` | `null`, ou un nom |
| `startsAt`, `endsAt` | `null`, ou la période ; la fin vient après le début |
| `setPrice(PriceTier)`, `removePrice(sku, minQuantity)` | Un prix par *SKU* et par quantité, dans la devise de la grille |
| `tiers()`, `priceFor(sku, quantity)`, `appliesTo(group, channel, at)` | Lisent la grille |
| `withdraw()`, `restore()` | Une grille retirée ne s'applique à rien |

`PriceTier::of(sku, minQuantity, unitPrice)` : un *SKU*, à partir d'une unité, à zéro ou plus.

Les paliers sont gardés triés par *SKU* dans l'ordre des octets (`10` avant `9`, les chiffres avant les lettres), puis
par quantité, ce qui est l'ordre dans lequel `tiers()` les liste. `setPrice()` trouve la place d'un palier par
recherche binaire : définir chaque palier d'une grande grille coûte une recherche, pas un tri de toute la grille.

## La lecture des grilles

`PriceBookPrices` lit et classe les grilles actives une seule fois, avec leurs paliers indexés par *SKU*, et les garde
jusqu'à `reset()` : un panier dont le prix est recalculé à chaque changement interroge le dépôt une fois, pas une fois
par ligne. Le module étiquette le service `kernel.reset`, si bien que FrameworkBundle le réinitialise entre les requêtes
et les messages d'un *worker* de longue durée. Une grille créée ou modifiée au milieu d'une requête s'applique à partir
de la suivante.

## Stockage

Avec DoctrineBundle, une grille de prix est une rangée de `phil_commerce_catalog_price_book`, ses paliers dans une
colonne JSON.

| Colonne | Type | Contient |
|---|---|---|
| `code` | `VARCHAR(64)`, clé primaire | Le code |
| `name` | `VARCHAR(255)` | Le nom |
| `after_books` | JSON | Les codes des grilles après lesquelles elle est essayée |
| `currency` | `CHAR(3)` | La devise |
| `customer_group`, `channel` | `VARCHAR(64)`, nullable | Les conditions |
| `starts_at`, `ends_at` | `DATETIME` de type `datetime_utc_immutable`, nullable | La période, écrite et relue en UTC |
| `tiers` | JSON | Le *SKU*, la quantité minimale, le prix unitaire en unités mineures |
| `active` | `BOOLEAN`, indexé par `phil_commerce_catalog_price_book_active` | `false` une fois retirée |

Le dépôt lit les grilles actives par code sur cet index ; `PriceBookOrder` les classe.

## Passer des grilles classées par numéro

Une table dont les grilles sont classées par une colonne numérique `priority` passe aux listes `after` avec
`PriceBookOrderMigration`, depuis une migration Doctrine de l'application :

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\PriceBookOrderMigration;

final class Version20261112000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        PriceBookOrderMigration::run($this->connection);
    }
}
```

Elle garde l'ordre dans lequel les grilles étaient essayées. Chaque grille est placée après chaque grille de priorité
plus haute, dans l'ordre où elles étaient essayées, les 1 000 plus proches au plus ; les grilles d'une même priorité
restent dans l'ordre de leurs codes. Les grilles retirées sont migrées aussi, pour que la restauration de l'une la
remette à sa place.

| Étape | Ce qui change |
|---|---|
| 1 | La colonne `after_books` est ajoutée, nullable |
| 2 | Chaque rangée reçoit sa liste |
| 3 | `after_books` devient obligatoire ; `priority` et chaque index qui la contient sont supprimés ; l'index sur `active` est ajouté s'il n'en existe pas |

`run()` retourne `false`, sans rien changer, sur une table déjà sans `priority`. La table correspond alors au mappage,
si bien que `doctrine:migrations:diff` ne trouve plus rien à changer. Testé sur SQLite, MySQL 8 et PostgreSQL 17.
