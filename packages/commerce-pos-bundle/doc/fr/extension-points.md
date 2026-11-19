<!-- phil-dev source-sha256=07a25c2e6c24b3c44ed4f4ea92055eb95f94d5f0101bd1c4b58b0b7ed03773ae -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application implémente, configure ou utilise. Les
ports du noyau et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette |
|---|---|---|
| `PaymentGateway` | `RegisterGateway`, pour `cash` et les méthodes enregistrées | `phil_commerce.payment_gateway` |
| `PaymentGateway` | `TerminalGateway`, pour la méthode des terminaux connectés | `phil_commerce.payment_gateway` |

## `PaymentTerminal`

`Phil\CommercePosBundle\Application\Contract\PaymentTerminal` — un adaptateur par processeur de terminaux connectés,
étiqueté `phil_commerce_pos.payment_terminal` par l'autoconfiguration et trouvé par son `name()`. Son contrat, et la
façon d'en écrire un, sont dans [terminals.md](terminals.md#écrire-un-adaptateur). Le module fournit
`FakePaymentTerminal`, enregistré avec `terminals.fake: true`.

## `TillDrawForm`

`Phil\CommercePosBundle\Application\Contract\TillDrawForm` — le formulaire qui saisit au comptoir les tirages d'un
tireur, de vrais dés lancés devant le client ; le paquet qui fournit le tireur le fournit, étiqueté
`phil_commerce_pos.till_draw_form` par l'autoconfiguration. Voir
[counter.md](counter.md#tirages-saisis-au-comptoir).

## `OfflineSaleGate`

`Phil\CommercePosBundle\Application\Contract\OfflineSaleGate` — si la personne qui envoie une vente hors ligne peut
l'importer. Enregistré sous l'interface, il est injecté automatiquement dans `OfflineSales` ; sans lui, une vente est
prise telle qu'envoyée. Voir [Ventes hors ligne](offline.md#qui-peut-envoyer-une-vente).

| Méthode | Contrat |
|---|---|
| `allows(OfflineSale $sale, CashSession $session): bool` | Si l'utilisateur connecté peut envoyer des ventes enregistrées par le caissier de la vente (`by`) dans cette session ; interrogé avant que quoi que ce soit soit lu ou écrit, un refus étant `OfflineSaleForbidden` |

## Dépôts

`RegisterRepository`, `CashSessionRepository` et `RegisterSaleRepository` sont des alias publics de
`DoctrinePosRepository` ; une application les fait pointer vers un service à elle qui respecte les mêmes contrats.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `recorded_methods` | Les méthodes que la caisse enregistre sans les traiter : un terminal qui n'est pas connecté, un chèque |
| `tip_label` | Le libellé d'un pourboire sur le reçu |
| `layaway_deposit` | La part d'une commande payée avant sa mise de côté, en parties par million |
| `cash_difference_threshold` | L'excédent ou le manque d'argent comptant, en unités mineures, au-delà duquel le rapport des écarts de caisse signale une session |
| `terminals.method` | La méthode de paiement du noyau des terminaux connectés, `card_present` par défaut |
| `terminals.fake` | Enregistre l'adaptateur de terminal factice, pour les tests et le développement |
| `cashier_pin.enabled`, `cashier_pin.limiter` | Le changement de caissier par NIP, et l'identifiant de service de la fabrique de son limiteur de débit |

## Les services et le navigateur

| Point | Comment |
|---|---|
| `Registers`, `CashSessions`, `RegisterCheckout`, `RegisterPayments`, `Layaways`, `OfflineSales` | La caisse dans le code ; publics — voir [La caisse](register.md) et [Mises de côté](layaways.md) |
| `TerminalPayments`, `SalePresets`, `CustomerDisplay`, `TillDraws`, `CashDifferences`, `CashierSwitch` | Publics — voir [terminals.md](terminals.md), [counter.md](counter.md) et [devices.md](devices.md) |
| `OfflineSaleController`, `TerminalNotificationController` | Routés par l'application ([installation.md](installation.md#routage)) |
| La file d'attente hors ligne | Le script propre à la caisse émet `pos:sale` avec la vente ; le contrôleur `offline-queue` la garde dans la base IndexedDB que nomme sa valeur `database`, l'envoie à sa valeur `url`, et émet `flushed` avec les décomptes |
