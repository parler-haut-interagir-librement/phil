<!-- phil-dev source-sha256=0c35511823db73df391f45809aaa486690391d9cdbbd927f1bb7d6deaf5841b5 -->
# phil/commerce-bundle

**Les commandes, les prix, les taxes, les paiements et les reçus d'une boutique Symfony, avec des modules branchés par-dessus.**

phil/commerce-bundle est le noyau du commerce dans une application Symfony. Il tient les commandes et leurs ajustements,
avec des totaux toujours calculés dans une seule devise, au fil de leurs états : panier, soumission, passée, annulée. Il
vend par des canaux, un site avec ses propres domaines ou un point de vente, chacun avec sa devise, sa locale et sa zone
de taxe. Il garde avec chaque ligne les valeurs des options que déclare un catalogue — un texte, un choix avec son
image, une encre, une valeur tirée une fois la commande payée —, si bien que le même produit avec d'autres valeurs forme
une autre ligne, et il facture ce qu'elles ajoutent. Il taxe chaque ligne après ses promotions, avec les taux de sa
configuration ou du résolveur de l'application. Il prend les paiements par les passerelles que branche l'application, et
ne fait avancer chaque paiement que sur les notifications vérifiées de son processeur. Il émet le reçu d'une commande
passée et les notes de crédit qui l'annulent, chaque série numérotée sans trou, et en produit le rendu sous forme de
facture PDF. Il permet de payer plus tard une commande passée — une précommande, des versements, une mise de côté — sous
une seule politique : un dépôt, un solde appelé par une date, une expédition, l'arrivée de la marchandise ou quelqu'un
de la boutique, et le stock engagé à la commande ou au solde. Son cycle de vie est un workflow de `symfony/workflow` qui
journalise chaque transition et laisse une personne autorisée en forcer une ; une commande passée change par des
changements versionnés avec leur différence due, et elle expire quand personne ne la paie. Il offre une commande en
cadeau, emballée ou non, garde des listes de souhaits partagées où d'autres achètent, prend les rabais accordés à la
main, prend les paiements hors ligne et les liens de paiement qui expirent, rembourse en deux temps, numérote chaque
type de document d'un registre, et annonce une commande payée en entier, remboursée ou annulée à qui écoute. Il ne
convertit jamais de devises, ne décide pas de l'inscription aux taxes ni du lieu de fourniture, ne stocke pas
lui-même les états des tentatives de paiement, ne parle pas à un processeur de paiement et ne crédite pas une partie
de la quantité d'une ligne.

Il sert à une application qui vend, qu'il s'agisse de biens ou d'accès. La plupart des boutiques vendent des accès :
elles installent le noyau et peut-être `phil/commerce-subscription-bundle`, jamais un catalogue. Les produits, les
grilles de prix et les promotions relèvent de `phil/commerce-catalog-bundle` ; la logistique et la vente en gros sont
des paquets à part. Un module refuse de se charger sans le noyau. Le bundle se passe de registre de ressources et de
fabriques génériques.

C'est un bundle Symfony qui stocke les commandes, les paiements et les reçus par Doctrine, et qui expose un point de
terminaison de *webhook* pour les notifications de paiement. Les montants sont le `Money` de `phil/money`, et les
taxes sont calculées par `phil/tax`.

La conception repose sur des ports câblés par autoconfiguration : ce qui est vendu (`SellableResolver`), avec quelles
options (`SellableOptions`), à quel prix unitaire (`UnitPriceResolver`), les processeurs qui recalculent une commande
après chaque changement (`OrderProcessor`), ce que vend un canal (`SellableAvailability`), les passerelles de
paiement (`PaymentGateway`) et qui tire une option après le paiement (`OptionDrawer`). Un limiteur des tentatives de
paiement, écrit en fonctions pures, garde un seul état par clé ; un verdict de robot ajoute de la friction et ne
refuse jamais à lui seul.

## État

| Partie | État |
|---|---|
| `Order`, `OrderLine`, `Adjustment`, `SellableReference`, `Sellable`, `PricingContext` | Implémenté et testé |
| Les états d'une commande — panier, soumission, passée, annulée —, les soumissions, la modification d'une commande passée et le stockage sur Doctrine | Implémenté et testé |
| Les canaux : les sites et les points de vente, leurs domaines, leur devise, leur locale, leur thème et leur sélection | Implémenté et testé |
| Les paiements : les paiements fractionnés, les passerelles par processeur, les notifications vérifiées appliquées une seule fois, les paiements abandonnés, les remboursements, les contestations, le point de terminaison de *webhook* | Implémenté et testé |
| Les ventes différées — précommandes, versements, mises de côté — en une seule politique : dépôt, déclencheur du solde, engagement du stock, événements du domaine et le port `StockCommitments` | Implémenté et testé |
| `OrderModifier` sur `SellableResolver`, `UnitPriceResolver`, `OrderProcessor`, `SellableAvailability` | Implémenté et testé |
| Les options : `OptionSet`, `OptionDefinition`, `LineConfiguration`, les lignes configurées, `OptionSupplementProcessor`, `OptionDraws`, sur `SellableOptions` et `OptionDrawer` | Implémenté et testé |
| `TaxOrderProcessor`, `ConfiguredTaxRates` et `phil_commerce.tax_rates` | Implémenté et testé |
| `PaymentAttemptKey`, `PaymentAttemptPolicy`, `PaymentAttemptState`, `PaymentAttemptDecision`, `PaymentAttemptLimiter` | Implémenté et testé |
| `TaxRateResolver`, `PaymentAttemptStateStore` | Des ports ; l'application les implémente, `ConfiguredTaxRates` par défaut pour le premier |
| Les reçus et les notes de crédit, numérotés sans trou, et leur facture PDF | Implémenté et testé |
| Le cycle de vie d'une commande en workflow `phil_commerce_order`, son journal, les transitions forcées et les statuts | Implémenté et testé |
| Les options de cadeau, le processeur d'emballage-cadeau et les listes de souhaits partagées | Implémenté et testé |
| Les rabais manuels sur une ligne ou sur la commande | Implémenté et testé |
| Les paiements hors ligne, les liens de paiement qui expirent, les remboursements en deux temps, `OrderPurchased`, `OrderRefunded`, `OrderCancelled` | Implémenté et testé |
| Les changements des commandes passées, préparés, confirmés et versionnés | Implémenté et testé |
| L'expiration et la suppression des commandes impayées, par canal | Implémenté et testé |
| Le registre des documents, chaque type listé, par lien direct seulement, ou gardé pour la boutique | Implémenté et testé |
| Les crédits sur une partie de la quantité | Pas commencé |
| La visibilité de ce qui est vendu — listé, par lien direct seulement, absent —, commandable ou non, avec prix ou non | Implémenté et testé ([canaux](doc/fr/channels.md#la-sélection-dun-canal)) |
| Les abonnements, le catalogue, la logistique, la vente en gros, le point de vente, les retours, la valeur stockée, la syndication | Leurs propres paquets, chacun implémenté et documenté dans son README ([modules](doc/fr/modules.md)) |

## Documentation

- [Installation](doc/fr/installation.md) : les prérequis, l'installation, l'enregistrement du bundle et de ses
  modules, et la déclaration des services et des adaptateurs qu'utilise l'application.
- [Configuration](doc/fr/configuration.md) : `phil_commerce.tax_rates`, les services qu'enregistre le bundle, et
  chaque décision que prend l'application.
- [Modules](doc/fr/modules.md) : les paquets de modules, comment ils se branchent, la référence de conception et les
  choix consignés pour ce qui n'est pas commencé.
- [Canaux](doc/fr/channels.md) : les sites et les points de vente, le canal d'une requête, la sélection d'un canal.
- [Commandes](doc/fr/orders.md) : les commandes, les lignes, les ajustements, les états, le stockage et
  `OrderModifier`.
- [Options](doc/fr/options.md) : les options que déclare un catalogue, la configuration d'une ligne, ce qu'ajoutent
  les options, les tirages après le paiement, le stockage et les reçus.
- [Argent](doc/fr/money.md) : les montants et les devises de `phil/money`, et ce sur quoi s'appuie le bundle.
- [Paiements](doc/fr/payments.md) : le paiement d'une commande passée, les paiements fractionnés, les notifications,
  le point de terminaison de *webhook*, les remboursements et ce qu'une commande a reçu.
- [Ventes différées](doc/fr/deferred-sales.md) : les précommandes, les versements et les mises de côté en une seule
  politique, ses états, les soldes échus, les événements et le port du stock.
- [Reçus](doc/fr/receipts.md) : l'émetteur, l'émission dans une transaction, les notes de crédit, la numérotation et
  le stockage.
- [Cycle de vie](doc/fr/lifecycle.md) : le workflow d'une commande, son journal, les transitions forcées et les
  statuts.
- [Cadeaux et listes de souhaits](doc/fr/gifts.md) : les options de cadeau, l'emballage, les listes de souhaits
  partagées par un lien.
- [Rabais manuels](doc/fr/discounts.md) : les rabais accordés à la main, sur une ligne ou sur la commande.
- [Changements d'une commande passée](doc/fr/order-changes.md) : préparés, confirmés, versionnés, avec leur différence
  due.
- [Expiration](doc/fr/expiry.md) : les commandes impayées expirées puis supprimées, par canal.
- [Liens de paiement](doc/fr/payment-links.md) : les liens qui ouvrent une commande passée jusqu'à leur expiration.
- [Remboursements](doc/fr/refunds.md) : les remboursements accordés, puis exécutés.
- [Registre des documents](doc/fr/documents.md) : les types de documents, leurs séries et leur visibilité.
- [Taxes](doc/fr/taxes.md) : le processeur de taxes, les taux configurés, le port du résolveur, et ce qui vient de
  `phil/tax`.
- [Tentatives de paiement](doc/fr/payment-attempts.md) : les règles du limiteur, la politique, les clés par commande
  et par réseau du client, l'état unique, les décisions avec `Retry-After`, et une tentative de bout en bout.
- [Points d'extension](doc/fr/extension-points.md) : les seize ports, méthode par méthode, les contrats de
  stockage, les événements et ce sur quoi s'appuie un module de retours, leurs invariants et des exemples
  d'adaptateurs.
- [Tests](doc/fr/testing.md) : comment le bundle est testé, comment lancer ses tests, et comment une application teste
  ses commandes, ses taxes, son limiteur et son stockage.
- [Référence](doc/fr/reference.md) : l'API publique par couche, une ligne par type, et ce qui n'est pas commencé.

## Démarrage rapide

```bash
composer require phil/commerce-bundle
```

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    tax_rates:
        goods:
            QC: { GST: '5', QST: '9.975' }
```

```php
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

// $modifier: Phil\CommerceBundle\Application\OrderModifier, from the container
$order = Order::open('2026-0001', Currency::of('CAD'));
$modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, $now));
$modifier->changeTaxZone($order, 'QC');
$order->total(); // the lines, then GST and QST on each line

$limiter = new PaymentAttemptLimiter(PaymentAttemptPolicy::of(5, 30, 900));
$key = PaymentAttemptKey::forOrder($order->number);
$decision = $limiter->decide($store->find($key), $now);
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-filter`, `ext-json`, `ext-mbstring` et `ext-sodium` ; Doctrine ORM 3.7 et DBAL 4.5 ;
`phil/ddd-bundle`, `phil/money`, `phil/paged-media` et `phil/tax` 0.1 ; `psr/clock` 1.0 ;
`symfony/event-dispatcher-contracts` 3.6 ; `symfony/config`, `symfony/dependency-injection`,
`symfony/http-foundation`, `symfony/uid` et `symfony/workflow` 8.2 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).

Chaque fichier est sous cette seule licence. Les composants de Sylius ont servi de modèle de conception seulement.
