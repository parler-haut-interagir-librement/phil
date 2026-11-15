<!-- phil-dev source-sha256=a04c63a1866c4056fa86de1ab6fb53bb7aea763e02b9448868f90d65c1295a81 -->
# phil/product-feeds

**Flux de produits pour les comparateurs et les places de marché, écrit dans un fichier en mémoire constante puis mis
en place d'un seul coup.**

phil/product-feeds publie un catalogue comme un fichier de flux que les comparateurs et les places de marché
récupèrent à intervalles réguliers : du RSS 2.0 avec l'espace de noms `g:` des données de produit, ou des valeurs
séparées par des tabulations avec une ligne d'en-tête. Les deux formats portent les champs de la spécification de
données de produit que lit Google Merchant Center, et que lisent aussi d'autres lecteurs de flux de produits :
identifiant, titre, description, lien, images, disponibilité, prix et prix soldé avec sa période, état, marque, GTIN,
MPN, le groupe des variantes d'un produit, et la couleur, la taille, la matière et le motif qui les distinguent, la
tranche d'âge et le genre des vêtements, la catégorie du produit, les frais de livraison par pays et par service, le
poids d'expédition, les multipacks et les ensembles, et cinq étiquettes personnalisées.

Il s'adresse à une boutique qui veut que ses produits soient listés au-delà de ses propres pages. Un `FeedItem`
vérifie chaque champ à sa création — texte nettoyé de ce que XML ne peut pas contenir, identifiants en ASCII, GTIN dont
la clé de contrôle est valide, prix de `phil/money`, adresses de `phil/url` —, si bien qu'un fichier ne contient jamais
un article qu'un lecteur refuse pour sa forme. `FeedPublisher` écrit les articles un à un dans un fichier temporaire à
côté du flux, puis le renomme par-dessus le flux : un lecteur récupère toujours un fichier complet, et un catalogue de
cent mille produits s'écrit dans la mémoire d'un seul.

C'est une bibliothèque PHP simple. Le catalogue arrive par un seul port, `ProductSource` ; un bundle Symfony qui détient
des produits l'implémente et planifie le fichier, comme le fait `phil/commerce-catalog-bundle`. Pousser les produits
vers l'API d'une place de marché, article par article, n'est pas écrit : le fichier est ce que tous les lecteurs
prennent, et un adaptateur d'API attend un site qui a besoin de changements vus en quelques minutes.

## État

| Partie | État |
|---|---|
| `FeedItem`, `Availability`, `Condition`, `AgeGroup`, `Gender`, `SalePeriod`, `Shipping`, `FeedChannel` | Implémenté et testé |
| `FeedPublisher` : le fichier écrit en mémoire constante, renommé en place | Implémenté et testé |
| RSS 2.0 avec l'espace de noms `g:`, et TSV | Implémenté et testé |
| `ProductSource` et `FeedSerializer`, les points d'extension | Implémenté et testé |
| Pousser les produits vers l'API d'une place de marché | Une étape ultérieure, pour un site qui en a besoin |

## Documentation

| Document | Contenu |
|---|---|
| [Flux](doc/fr/feeds.md) | Les articles et leurs champs, les deux formats, la sûreté du fichier, sa publication, sa planification. |
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Points d'extension](doc/fr/extension-points.md) | La source des produits, le sérialiseur d'un autre format. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/product-feeds
```

```php
use Phil\ProductFeeds\{Availability, FeedChannel, FeedFormat, FeedItem, FeedPublisher};
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\Money\{Currency, Money};
use Phil\Url\Url;

$source = new class implements ProductSource {
    public function items(): iterable
    {
        yield new FeedItem(
            'DICE-1',
            'Dice set',
            Url::fromString('https://shop.example/products/DICE-1'),
            Money::of(2_500, Currency::of('CAD')),
            Availability::InStock,
            imageLink: Url::fromString('https://shop.example/images/DICE-1.jpg'),
            brand: 'Gnome',
            gtin: '4006381333931',
        );
    }
};

$channel = new FeedChannel('The Dice Shop', Url::fromString('https://shop.example/'), 'Dice and games.');
$feed = new FeedPublisher()->publish($source, $channel, FeedFormat::Xml->serializer(), '/var/www/feeds/products.xml');

echo $feed->items; // 1
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` ; `phil/money` 0.1 et `phil/url` 0.1.4, qui exige les
extensions intl et URI.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
