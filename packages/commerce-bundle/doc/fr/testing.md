<!-- phil-dev source-sha256=811e7ea0f633fded80aef61bd8ff4218b77c6d930bc9a0efe204fa4e7cf37f18 -->
# Tests

## Comment le bundle est testé

Les tests unitaires vivent dans `tests/Unit`. Aucun ne démarre un noyau, n'ouvre une connexion ni ne lit l'horloge :
chaque heure est passée en argument.

| Domaine | Ce qui est couvert |
|---|---|
| `Order`, `OrderLine` | Les lignes fusionnées par référence et gardées dans l'ordre, les quantités et les prix refusés sous leurs bornes, les devises, les ajustements remplacés par type et refusés sans changement, les ajustements neutres exclus des totaux, les ajustements d'une ligne retirée retirés |
| Les états d'`Order` | Le passage, l'annulation, les passages refusés, une commande passée ou annulée qui ne change plus, un panier vide qui ne peut pas être passé |
| Le mappage d'`Order` | La table, l'identifiant, la version, l'index et chaque colonne, tels que les crée la migration d'une application |
| `OrderLinesType`, `AdjustmentsType` | Le JSON écrit et relu, l'Unicode et les barres obliques non échappés, chaque valeur stockée mal formée ou refusée changée en erreur de conversion DBAL avec sa cause |
| `Channel`, `Channels` | Chaque règle d'un canal et de ses noms d'hôte, le canal d'un hôte, les commandes qu'il ouvre, sa table |
| `Visibility`, `Offer`, `SellableOffers` | Les trois niveaux et le plus strict de deux, une offre commandable refusée sans son prix, une offre cachée jamais commandable ni avec prix, la réponse la plus stricte de chaque sélection, l'interrogation arrêtée à la première réponse cachée |
| `OptionDefinition`, `AmountRange`, `LineOptions` | Chaque nature déclarée, vérifiée et décrite ; un montant dans sa plage, écrit en unités mineures, refusé hors d'elle ou écrit autrement, facturé et taxé sur sa ligne, un autre montant une autre ligne ; la cible de fuzzing `chosen-amount` sur des montants que personne n'a écrits |
| `Payment` | La création, les modes refusés, chaque passage que permet chaque événement et chacun qu'il refuse, une notification appliquée une seule fois et une notification refusée non enregistrée, les remboursements qui s'additionnent, ce que tient un paiement selon son état ; son mappage |
| `OrderPayments`, `PaymentNotification`, `PaymentStart` | Les paiements fractionnés qui réservent ce qui est en cours, les remboursements et les contestations perdues rendus, le montant à payer jamais négatif, les cinq états ; les refus de chaque objet-valeur, une notification ignorée qui ne nomme aucun paiement |
| `Payments` | Le début de tout ou partie de ce qui reste, les montants et les devises refusés, les paniers et les modes inconnus refusés, les notifications appliquées une seule fois, refusées ou pour un paiement inconnu, les remboursements demandés et enregistrés seulement à la confirmation |
| `PaymentWebhookController` | 204, 400 et 404 pour chaque issue, un commit seulement pour une notification dont la réception est confirmée, une notification ignorée journalisée et non validée, les en-têtes remis sous forme de listes de chaînes |
| `Receipt`, `Issuer` | Une copie de la commande passée gardée telle qu'émise, les taxes additionnées par code, les notes de crédit totales et partielles, les ajustements de la commande avec la dernière ligne, chaque refus ; son mappage et celui des séquences |
| `Receipts` | Un reçu par commande, numéroté dans sa série ; les notes de crédit dans la leur ; aucun numéro pris par un document refusé |
| `DocumentType`, `DocumentRegister`, `CoreDocumentTypes` | Les bornes d'un type, ses numéros, un code ou un préfixe déclaré deux fois, la série demandée dans la transaction, la visibilité de chaque type et les types que montre la liste d'un acheteur, cachés par défaut |
| `SellableReference`, `Sellable`, `PricingContext`, `Adjustment` | Les bornes des types, des identifiants et des libellés, les refus avec leurs messages |
| `OrderModifier` | Le résolveur du type répond seul, les prix demandés dans l'ordre pour toute la ligne, chaque processeur après chaque changement, les articles vendables inconnus, une ligne qu'aucune commande du canal ne peut contenir refusée, une que seul son lien direct atteint vendue |
| `TaxOrderProcessor`, `ConfiguredTaxRates` | Chaque ligne par chaque taux de sa catégorie, après ses propres promotions seulement ; pas de zone, pas de taxe ; idempotent ; les pourcentages lus et refusés ; les taxes retirées des prix qui les incluent, neutres |
| `PaymentAttemptKey` | Les clés de commande et d'IP n'entrent jamais en collision ; les graphies IPv6 et les adresses d'un même /64 partagent une clé ; les longueurs de préfixe sur et à travers les frontières d'octets, de 1 à 128, effacent exactement les bits au-delà ; les adresses IPv4 mappées ; les refus |
| `PaymentAttemptPolicy`, `PaymentAttemptState`, `PaymentAttemptDecision` | Les limites de la règle de la fenêtre, les trois champs d'un état, l'expiration, l'arrondi de `Retry-After`, la décision la plus restrictive |
| `PaymentAttemptLimiter` | Le budget, les pénalités qui s'accumulent en une seule attente, le plafond, l'expiration qui repart d'un état vide, les tentatives pendant une attente non enregistrées, la durée de vie |
| `Quote`, `Quotes`, `OrderAmendments` | Un devis recalculé, figé et passé à ses prix, refusé et ramené à un panier, expiré ; une commande modifiée jusqu'à son reçu, ce qui a été payé au-delà du total modifié |
| `DeferredSale`, `DeferredSales`, `Deposit`, `DeferredSalePolicy` | Une mise de côté de la commande à son solde, un dépôt payé avant l'ouverture de la vente, une vente par commande, les soldes échus appelés du plus ancien au plus récent par lots bornés, une vente annulée qui libère son stock avant que ses écouteurs l'apprennent |
| `OrderWorkflow`, `OrderLifecycle`, `OrderStateChange` | Chaque transition et les passages refusés, le journal, les passages forcés refusés par défaut et annoncés par `OrderStateForced`, les statuts effacés par chaque passage, `OrderCancelled` une fois par annulation — annulée, expirée ou forcée — et jamais pour un passage forcé hors de `cancelled` |
| `GiftOptions`, `GiftWrapping`, `ManualDiscount`, `ManualDiscountProcessor` | Un emballage par commande emballée, l'emballage recyclé, une boutique qui n'emballe pas ; des rabais sur une ligne ou sur toute la commande avant les taxes, jamais sous zéro, répartis sur ce qui reste de chaque ligne |
| `Payments` qui annonce, paiements hors ligne | `OrderPurchased` une fois par commande payée en entier, à l'horloge de l'application, jamais pour une notification refusée ; paiements hors ligne déclarés puis confirmés, jamais abandonnés comme périmés, remboursés sans passerelle |
| `WishList`, `WishLists`, `PaymentLink`, `PaymentLinks` | Des jetons montrés une fois et gardés hachés, une liste où l'on achète et comptée sur `OrderPurchased`, des liens qui ouvrent leur commande jusqu'à ce qu'ils expirent, soient révoqués ou que la commande soit payée |
| `OrderChange`, `OrderChanges`, `OrderExpiry`, `Refund`, `Refunds` | Des changements préparés, confirmés, versionnés et refusés sur une révision plus ancienne ou après un reçu ; le balayage par canal, borné, avec ou sans le workflow ; des remboursements accordés puis exécutés, jamais au-delà de ce qui reste, comptés à partir des remboursements que lit le verrou après que l'état du paiement a été relu, `OrderRefunded` une fois par remboursement exécuté, avec le canal et le client de la commande ou sans eux quand la commande est introuvable |
| `UnknownShipments` | Aucune commande ni ligne connue comme partie |
| `ReceiptPdf` | Un reçu et une note de crédit imprimés, les longues lignes et les longs reçus sur plusieurs pages, les options sous leur ligne, les polices intégrées, PDF/A |
| `OrderWorkflowPass`, `DoctrineDocumentNumbering`, `DoctrineDeferredSaleRepository` | Le workflow ajouté au registre quand il y en a un ; les requêtes des séries et des ventes différées |
| `PhilCommerceBundle` | L'alias `phil_commerce`, les quatre étiquettes que lit `OrderModifier` et l'étiquette des passerelles, l'autoconfiguration, le processeur de taxes et son résolveur, un taux refusé, les catégories, les zones et les codes à tirets gardés tels qu'écrits, les préfixes de reçus trop longs pour la colonne du numéro ou partagés par les deux séries |

Une suite fonctionnelle, sous `tests/Functional/`, démarre un vrai noyau, `tests/Functional/App/TestKernel.php`
(FrameworkBundle et le bundle avec la TPS, la TVQ et la TVH sous `phil_commerce.tax_rates`), avec son cache dans un
répertoire à lui sous le répertoire temporaire du système. `BundleRegistrationTest` fixe l'alias, la référence
qu'imprime `config:dump-reference phil_commerce`, et les échecs de construction que causent un taux refusé et une clé
inconnue. `ApplicationWiringTest` branche un résolveur d'articles vendables et une liste de prix par
autoconfiguration, comme le ferait un module, construit et taxe une commande par `OrderModifier`, et épuise un budget
du limiteur avec un stockage en mémoire câblé à partir de paramètres, comme le montre
[installation.md](installation.md). `OrderStorageTest` passe une commande et la relit en entier dans une nouvelle
unité de travail, sur DoctrineBundle et une base SQLite en mémoire, et voit un changement écrit avec sa version
avancée. `PaymentFlowTest` paie une commande passée par une passerelle que branche l'application, livre la
notification signée du processeur au contrôleur de *webhook*, et relit le paiement et la commande payés.
`ReceiptFlowTest` émet des reçus et une note de crédit dans des transactions, les relit tels qu'émis, voit un reçu
annulé par un retour arrière rendre son numéro, et un numéro refusé hors d'une transaction. `SalesLifeStorageTest`
stocke un cadeau passé par le workflow et journalisé, des liens, des changements, des remboursements — refusés hors d'une transaction et sur un paiement modifié depuis sa lecture — et le registre, et
balaie les commandes impayées par leur canal ; `DeferredSaleStorageTest` stocke une mise de côté et appelle son solde
à l'échéance ; `SalesConfigurationTest` construit avec les valeurs par défaut et refuse ce que le bundle ne peut pas
utiliser. Quand le bundle est installé seul, la suite fonctionnelle est sautée : Symfony ne câble aucun répartiteur
d'événements quand FrameworkBundle est un prérequis de développement.

Aucune des deux suites n'a besoin d'une connexion ni d'une variable d'environnement : les tests de stockage
s'exécutent sur une base SQLite en mémoire, et `vendor/bin/phpunit packages/commerce-bundle/tests/Functional` lance la
suite fonctionnelle seule. Pour les lancer sur un serveur, `PHIL_COMMERCE_TEST_DATABASE_URL` le nomme ; chaque test de
stockage supprime les tables du bundle et les crée de nouveau :

```bash
eval "$(vendor/bin/phil-dev services env)"   # after vendor/bin/phil-dev services up
PHIL_COMMERCE_TEST_DATABASE_URL="$PHIL_TEST_MYSQL_URL" vendor/bin/phpunit packages/commerce-bundle/tests/Functional
PHIL_COMMERCE_TEST_DATABASE_URL="$PHIL_TEST_POSTGRESQL_URL" vendor/bin/phpunit packages/commerce-bundle/tests/Functional
```

`tests/Integration/` s'exécute sur les serveurs de `phil-dev services`, à partir de `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`, et est sauté sans eux. `MySqlRefundLockTest` et `PostgreSqlRefundLockTest` accordent deux
remboursements d'un même paiement à la fois : le second commis, dans un processus à lui (`grant-refund.php`, par
`symfony/process`), lit le paiement, attend pendant que la transaction du premier tient son verrou, puis est refusé
quand les deux ensemble dépassent le paiement, et accordé quand ils ne le dépassent pas.

Le mappage reste portable entre SQLite, MySQL 8.4 et PostgreSQL 17 : les colonnes `lines` sont entre guillemets, car
`LINES` est réservé dans MySQL, et aucune colonne JSON ne déclare de valeur par défaut, ce que MySQL refuse.

## Lancer les tests

### Depuis la racine du monodépôt qui incube le bundle

Les tests s'exécutent sur le `vendor/` de la racine :

```bash
vendor/bin/phpunit packages/commerce-bundle/tests
```

`composer packages` installe le bundle seul dans un nouveau répertoire, avec `phil/ddd-bundle`, `phil/money`, `phil/tax`
et `phil/paged-media` tirés de leur dépôt, vérifie ses dépendances déclarées avec `composer-dependency-analyser` selon
`composer-dependency-analyser.php`, puis lance ses tests.

### Installé seul

```bash
cd packages/commerce-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`phil/ddd-bundle`, `phil/money`, `phil/tax` et `phil/paged-media` viennent de Packagist, ou d'un dépôt `path` quand on
les travaille à côté du bundle.

## Tester une application construite sur le bundle

### Commandes et taxes

Construisez les commandes par `OrderModifier` avec vos propres résolveurs et processeurs, dans un test unitaire, et
vérifiez les unités mineures, ligne par ligne ; l'arrondi et les ventilations sont testés dans `phil/tax`, dont le
guide de test donne les cas à garder :

```php
$modifier = new OrderModifier([new GiftCards($denominations)], [], [new TaxOrderProcessor(new ConfiguredTaxRates([
    'goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']],
]))]);

$order = Order::open('1', Currency::of('CAD'));
$modifier->add($order, SellableReference::of('gift_card', '25'), 1, PricingContext::of(1, $now));
$modifier->changeTaxZone($order, 'QC');

self::assertSame(2_500, $order->total()->minorUnits); // gift cards are exempt
```

Gardez un test avec une promotion sur une ligne, pour voir la taxe calculée sur la ligne après rabais, et un test qui
exécute chaque processeur deux fois, pour voir que rien n'est ajouté deux fois.

### Le limiteur

Le limiteur prend l'heure en argument : pilotez-le avec des valeurs `DateTimeImmutable` fixes, ou une horloge
factice, et un stockage en mémoire :

```php
final class InMemoryPaymentAttemptStateStore implements PaymentAttemptStateStore
{
    /** @var array<string, array{PaymentAttemptState, int}> */
    public array $saved = [];

    public function find(PaymentAttemptKey $key): ?PaymentAttemptState
    {
        return $this->saved[$key->value][0] ?? null;
    }

    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void
    {
        $this->saved[$key->value] = [$state, $timeToLiveSeconds];
    }
}
```

Vérifiez que la durée de vie passée à `save()` est la fenêtre de la politique : c'est ce qui borne la durée pendant
laquelle une adresse du client est gardée.

### L'adaptateur du stockage

Testez contre le vrai stockage qu'un état enregistré disparaît après sa durée de vie, et qu'il se relit en un état
égal (`attempts`, `cumulativeWaitSeconds`, `lastAttemptAt` avec ses microsecondes).

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `OrderModifier::add()` et `Order::total()` sur des paniers
de 5, 20 et 100 lignes, et l'hydratation de la colonne des lignes par `OrderLinesType`. Depuis la racine du
monodépôt :

```bash
composer bench -- packages/commerce-bundle/tests/Benchmark
```
