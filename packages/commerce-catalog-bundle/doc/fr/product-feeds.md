<!-- phil-dev source-sha256=b2d320a7168d769e626f58c226fec486578cb141d59083a94bd8e00d1fa62112 -->
# Flux de produits

Avec `phil/product-feeds` installé, le module publie son catalogue comme des fichiers de flux que les comparateurs et
les places de marché récupèrent à intervalles réguliers : du RSS 2.0 avec l'espace de noms `g:` des données de produit,
ou du TSV. Chaque flux est déclaré dans la configuration et écrit d'un bloc par la bibliothèque, en mémoire constante,
puis renommé par-dessus le fichier précédent.

```sh
composer require phil/product-feeds
```

```yaml
# config/packages/phil_commerce_catalog.yaml
phil_commerce_catalog:
    product_feeds:
        google:
            path: '%kernel.project_dir%/public/feeds/google.xml'
            format: xml
            title: 'The Dice Shop'
            link: 'https://shop.example/'
            description: 'Dice, games and accessories.'
            channel: web
            locale: fr_CA
            product_link: 'https://shop.example/products/{sku}'
            image_link: 'https://shop.example/images/{sku}.jpg'
            attributes:
                color: colour
                gtin: ean
```

Les options sont décrites dans [Configuration](configuration.md#product_feeds).

## Ce qu'un flux publie

`CatalogProductSource` est le `ProductSource` de la bibliothèque pour un flux. Il lit les produits que le canal du flux
liste par `ProductListing`, deux cents par page, les familles omises : un produit laissé à son lien direct, ou caché,
n'est jamais publié, et un produit listé sans son prix est omis, puisque chaque article d'un flux en a un.

| Champ du flux | Lu depuis |
|---|---|
| `id` | Le *SKU* |
| `title` | Le nom dans la locale du flux, le long de la chaîne de `translations.locales` ; le nom propre du produit sans locale |
| `link` | `product_link`, `{sku}` et `{family}` remplacés, chacun encodé en pourcentage ; `{family}` est le *SKU* du produit lui-même quand il n'a pas de famille |
| `image_link` | L'attribut `image` quand il contient une adresse ; `image_link` sinon, construit de la même façon |
| `price` | Le prix de base |
| `sale_price` | Le prix des grilles de prix pour une unité, sans groupe de clients, le canal du flux et le moment où le flux est écrit, quand il est plus bas que le prix de base |
| `availability` | `out_of_stock` pour un produit qu'aucune commande ne peut contenir ; sinon l'attribut `availability` quand il nomme `in_stock` ou `out_of_stock`, sinon `in_stock` |
| `item_group_id` | Le *SKU* de la famille, pour une variante |
| `description`, `brand`, `gtin`, `mpn`, `condition`, `color`, `size`, `material`, `pattern`, `product_type` | L'axe de la variante, puis l'attribut, du nom que `attributes` donne au champ — son propre nom par défaut |

Un attribut `preorder` ou `backorder` est écrit `out_of_stock` : le catalogue ne tient aucune date de disponibilité, que
le lecteur exige avec eux. Les niveaux de stock sont ceux du module de logistique, que le flux ne lit pas ; un produit
épuisé pour de bon est rendu non commandable, et le flux l'écrit en rupture de stock.

Un produit que le flux ne peut pas porter — un GTIN dont la clé de contrôle échoue, un attribut d'image qui n'est pas une
adresse Web, un nom que la bibliothèque refuse — est omis et journalisé comme avertissement avec son *SKU* et la raison,
si bien qu'un seul produit n'arrête jamais le flux. Un flux écrit est journalisé comme information, avec ses articles et
ses octets.

## Publier

`ProductFeeds` publie un flux par son nom : `publish('google')` retourne le `PublishedFeed` de la bibliothèque. Trois
voies l'appellent :

| Voie | Quand |
|---|---|
| `bin/console phil:commerce-catalog:publish-feeds [feed…]` | À un déploiement ou depuis une tâche cron : chaque flux, ou ceux nommés ; le statut est un échec quand un flux échoue, les autres étant quand même publiés |
| Le message `PublishProductFeed` | Avec Symfony Messenger installé, `PublishProductFeedHandler` publie le flux qu'il nomme ; le message ne porte que le nom |
| `ProductFeeds::publish()` | Depuis le code de l'application, après une importation, par exemple |

Le calendrier est celui de l'application, avec Symfony Scheduler :

```php
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeed;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('feeds')]
final class FeedSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every('6 hours', new PublishProductFeed('google')));
    }
}
```

Un échec — un répertoire manquant ou non accessible en écriture, un disque plein — lève `FeedNotWritten`, et le fichier
précédent reste en place.

## Servir

Un fichier écrit sous le répertoire public est servi par le serveur Web à l'endroit où `path` le met ; on donne son
adresse au lecteur. Un fichier écrit ailleurs — `%kernel.project_dir%/var/feeds/google.xml`, par exemple — est servi
par la route que le bundle déclare, une fois que l'application l'importe :

```yaml
# config/routes/phil_commerce_catalog.yaml
phil_commerce_catalog:
    resource: '@PhilCommerceCatalogBundle/config/routes.php'
```

`GET /product-feeds/google` répond le fichier du flux `google` avec son type de média — `application/rss+xml` ou
`text/tab-separated-values` —, `X-Content-Type-Options: nosniff`, sa date `Last-Modified` et une étiquette d'entité faite
de son heure et de sa taille, jamais de son contenu. Les caches le gardent public pendant le `max_age` du flux, une
heure par défaut ; un lecteur qui renvoie l'étiquette ou la date reçoit `304 Not Modified`. Un flux non déclaré, ou pas
encore publié, est introuvable. Seul le nom du flux vient de l'adresse : le fichier est celui que nomme `path`.

## Ce qui n'est pas écrit

Pousser les produits vers la Merchant API de Google, ou celle d'une autre place de marché, article par article, n'est
pas écrit : chaque lecteur prend le fichier, et un adaptateur d'API est une étape ultérieure, pour un site dont les prix
ou le stock changent plus vite que son calendrier.
