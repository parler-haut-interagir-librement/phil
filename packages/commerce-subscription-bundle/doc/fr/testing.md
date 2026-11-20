<!-- phil-dev source-sha256=ec4d1442d0b9e21123116ca9fd5baa9e643e89d4c7641d6e5f0938c0e841af31 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `BillingPeriod` | Les jours, les semaines, les mois et les années à partir d'un point d'ancrage ; les fins de mois et les années bissextiles ; le fuseau horaire du point d'ancrage gardé ; les bornes |
| `Offer` | Chaque règle de `create()`, places comprises, le changement de prix, le retrait |
| `Subscription` | Chaque démarrage, paiement, échec, annulation, reprise, changement de palier et expiration, avec les refus de chacun ; les renouvellements comptés à partir du point d'ancrage ; un palier d'une autre période réancré ; un changement sur-le-champ, son crédit et ses refus ; l'abonnement d'un cadeau |
| `ImmediateChange`, `StableIdentifier`, `Gift` | La part du temps qui reste et son arrondi ; ce que refuse un identifiant ; un code lu tel que tapé, émis, remplacé, échangé une seule fois, et chaque refus |
| `Subscriptions`, `OfferSellables`, `FeatureAccess` | Les commandes passées par un vrai `OrderModifier`, rien de passé pour un abonnement qui n'est pas dû, le balayage, les tentatives configurées, les fonctionnalités des abonnements qui donnent accès |
| Calcul au prorata | Par le `TaxOrderProcessor` du noyau : une montée de palier dans la période, un deuxième changement, un changement de période, le jour du renouvellement, un essai, les refus en souffrance et annulé, le yen, les descentes de palier au renouvellement et sur-le-champ avec leur crédit reporté, un changement échoué, une commande qui ne peut pas être passée, les promotions sur la ligne |
| `Gifts`, `Seats`, organisations | Un cadeau commandé, payé, échangé et renouvelé ; chaque code refusé ; un bénéficiaire refusé pour une adresse courriel ; les membres d'organisations, les places attribuées, prises, libérées et en attente |
| Adaptateurs Doctrine | Chaque requête des quatre dépôts sur SQLite en mémoire, relue depuis une unité de travail neuve : les ordres, les limites, les places comptées avant celle d'un membre |
| Correspondance | Chaque colonne des quatre tables, telle que la crée la migration d'une application |
| `PhilCommerceSubscriptionBundle` | Les services, la configuration et ses bornes, les adaptateurs et la correspondance Doctrine, le refus sans le noyau |

Une suite fonctionnelle sous `tests/Functional/` démarre un vrai noyau, `tests/Functional/App/TestKernel.php`, avec
FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau du commerce et le module :
`SubscriptionFlowTest` commande, paie, balaie et renouvelle un abonnement par le conteneur et relit chaque étape,
`ChangesGiftsAndSeatsTest` calcule au prorata un changement avant que le noyau le taxe, trouve un cadeau par son code et
compte les places dans l'ordre ; le test d'enregistrement le démarre une fois sans le noyau du commerce pour voir
échouer la construction du conteneur. Symfony ne branche le répartiteur d'événements d'un tel noyau que lorsqu'une
application exige un noyau HTTP : installé seul, là où FrameworkBundle est un prérequis de développement, la suite est
sautée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-subscription-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `FeatureAccess::grants()` sur SQLite en mémoire, mémoïsé et
à la première vérification d'une requête, pour un abonné qui détient 1, 3 ou 10 abonnements, et pour un membre qui lit
les abonnements d'une organisation et sa place. Depuis la racine du monodépôt :

```bash
composer bench -- packages/commerce-subscription-bundle/tests/Benchmark
```
