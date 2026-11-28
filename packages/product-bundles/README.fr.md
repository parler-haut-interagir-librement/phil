<!-- phil-dev source-sha256=1722e7923c185460baa64a0ba91c6de9eae0ee52b679212b6c09de6296e35913 -->
# phil/product-bundles

**Vendez des produits comme un seul lot : prix fixe ou rabaissé, stock fixé par la pièce la plus rare, coût composé de
ses pièces, retours complets, lots partagés entre vendeurs.**

phil/product-bundles est une bibliothèque PHP simple. Elle définit ce que contient un lot, fixe son prix, dit combien
on peut en vendre et ce que coûte chacun, fige une vente sur sa ligne de commande et la retourne en entier.

**Un lot ne détient aucun stock en propre.** Un `ProductBundle` est une UGS (unité de gestion de stock) à lui, sur 1 à
50 composants, chacun avec les unités qu'un lot contient. Son stock est le plus petit des quotients des unités
disponibles de ses composants par ces unités : onze jeux de cartes et vingt livres de règles font cinq lots de deux
jeux et d'un livre, et les jeux fixent la limite. Une vente prélève les unités de chaque composant ; un retour les
remet.

**Deux prix.** Un lot a un prix fixe à lui, ou le prix de ses composants moins un taux en parties par million, arrondi
par un `\RoundingMode` nommé. Dans les deux cas, le prix se partage entre les composants selon ce qu'ils se vendent
séparément, et les parts s'additionnent au prix à l'unité près.

**Deux façons d'être taxé.** Un lot assemblé — un livre avec ses dés — se vend comme un seul produit, taxé sur son
prix entier par sa propre catégorie. Une collection — un livre imprimé et son PDF, tous les titres d'un créateur — est
taxée composant par composant, chacun à son propre taux sur sa part. Les deux se vendent sur une seule ligne et se
retournent en entier, jamais un composant seul. Un rabais entre deux produits vendus sur des lignes distinctes est une
promotion, non un lot.

**Un coût composé.** Ce que coûte un lot est la somme des coûts unitaires de ses composants multipliés par leurs
unités, tels que l'évaluation des stocks les dit maintenant — coût moyen pondéré ou premier entré, premier sorti,
selon le choix de la boutique. Un seul coût inconnu laisse le coût inconnu, jamais trop bas. La marge en découle, avec
son taux sur le prix.

**Une ligne figée.** `SoldBundle` garde ce qu'était la vente : le prix unitaire, la part et le coût unitaire de chaque
composant, la quantité vendue et retournée. Il est stocké avec la ligne de commande et relu, de sorte qu'un retour
rembourse ce qui a été payé et remet ce qui a été prélevé, quoi que le lot soit devenu depuis.

**Des lots partagés entre vendeurs.** Un `CooperativeBundle` rassemble les produits de plusieurs vendeurs : un hôte les
invite, chacun avec un poids relatif, et chaque participant approuve avant la vente. La première approbation verrouille
les participants et leurs poids ; un organisme de bienfaisance peut prélever d'abord une part de chaque vente. La
bibliothèque calcule la part de chaque participant ; la verser relève de l'application.

La bibliothèque ne lit que par des ports — les lots, les prix, le stock et les coûts des composants — et ne garde
aucun état entre les appels, de sorte que ses services sont sûrs dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Les lots, leurs composants et leurs politiques de prix ; la ligne stockée relue | Implémenté, testé et soumis au fuzzing |
| Le devis, les parts du prix, les économies | Implémenté et testé |
| Le stock fixé par le composant le plus rare | Implémenté et testé |
| Le coût composé et la marge | Implémenté et testé |
| La vente figée, ses prélèvements, les retours complets ; la ligne stockée relue | Implémenté, testé et soumis au fuzzing |
| Les lots coopératifs : approbations, verrou, part de l'organisme de bienfaisance, parts d'un revenu | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et ce que fournit l'application. |
| [Lots](doc/fr/bundles.md) | `ProductBundle`, ses composants, son genre et son stockage. |
| [Prix, stock et coût](doc/fr/price-stock-cost.md) | Les politiques de prix, les parts, le stock, le coût composé et la marge. |
| [Ventes et retours](doc/fr/sales-and-returns.md) | La ligne figée, les prélèvements, les retours complets et les taxes de chaque genre. |
| [Lots coopératifs](doc/fr/cooperative.md) | Participants, poids, approbations, verrou, organisme de bienfaisance et parts. |
| [Sécurité](doc/fr/security.md) | Les limites, ce qui est stocké et ce que doit être une ligne stockée. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, le fuzzing et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/product-bundles
```

```php
use Phil\Money\Currency;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\Bundles;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;

$bundleRepository->save(new ProductBundle(
    'STARTER', BundleKind::Assembled, Currency::of('CAD'),
    [new BundleComponent('RULES'), new BundleComponent('DICE', 2), new BundleComponent('SCREEN')],
    PricePolicy::discountOnComponents(Rate::ofPercent('15')),
));

$bundles = new Bundles($bundleRepository, new BundlePricing($prices), new BundleAvailability($stock), new ComposedCost($costs));

$bundles->quote('STARTER')->price;      // the components' prices less 15 %
$bundles->stock('STARTER')->quantity;   // limited by the scarcest component
$bundles->margin('STARTER')?->rate;     // null while a component's cost is unknown

$sold = $bundles->sell('STARTER', 2);   // frozen: store $sold->toArray() with the order line
foreach ($sold->draws() as $draw) {
    $logistics->issue($draw->sku, $draw->units);
}

$refund = SoldBundle::fromArray($line)->refund(1);   // one whole bundle back: $refund->amount, $refund->restock
```

## Prérequis

PHP 8.5 ou une version plus récente, phil/money 0.1 et phil/pricing 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
