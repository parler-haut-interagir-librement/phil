<!-- phil-dev source-sha256=0cf7d017fde4e4a3677d8acc72dbc082b3a9b49065826d4c47405a5798870b00 -->
# Ventes à crédit

Un acheteur — un compte, un locataire — achète à crédit : sur compte, jusqu'à une limite, chaque achat exigible après le
délai, 30 jours pour « net 30 ».

## Comptes

```php
$creditSales->open('tenant-42', Money::of(500_000, $cad), termsDays: 30);
$account = $creditSales->account('tenant-42');
$account->change(Money::of(1_000_000, $cad), 45);   // a new limit, new terms
$account->suspend();                                // no purchase on account until resume()
```

`owed` est ce que doit l'acheteur ; `available()` la limite moins ce montant, jamais sous zéro.

## Acheter à crédit

```php
$receivable = $creditSales->charge($order, 'tenant-42', $clock->now());   // or part of it, with an amount
```

L'achat est un paiement du noyau de méthode `on_account`, complété aussitôt, et une `Receivable` exigible après le délai
du compte. Un achat au-delà de ce qui est disponible est refusé avant que rien ne soit inscrit. Comme chaque paiement du
noyau, chacun forme sa propre transaction.

## Les paiements de l'acheteur

```php
$creditSales->receive($receivable->id, Money::of(250_000, $cad));   // a transfer, a cheque
$creditSales->overdue('tenant-42', $clock->now());
```

Un paiement est imputé jusqu'à concurrence de ce qui reste dû, la part prise étant retournée, et réduit ce que doit
l'acheteur. Une créance est en souffrance après sa date tant qu'il reste un solde.

## Remboursements

`Payments::refund()` du noyau atteint `AccountGateway`, qui crédite la créance jusqu'à concurrence de ce qui reste dû et
réduit ce que doit l'acheteur ; le remboursement est inscrit aussitôt sur le paiement, sous sa clé d'idempotence, pour
qu'un remboursement redemandé sous la même clé ne crédite rien de plus. Ce que l'acheteur a déjà payé au-delà lui est dû
en retour, et la créance le garde sous `refundDue()` :

```php
$creditSales->refundsDue('tenant-42');                                  // the receivables with something owed back
$creditSales->settleRefund($receivable->id, Money::of(1_500, $cad));    // given back by transfer or cheque
```

`settleRefund()` prend jusqu'à concurrence de ce qui est dû et retourne la part prise. La façon dont l'argent est rendu
— un virement, un chèque, une note de crédit — est le choix de l'application.

## Expédier avant le paiement

Avec `phil/commerce-logistics-bundle`, une commande part une fois que ses paiements couvrent ce qui est dû.
`CreditShipmentAuthorization`, enregistrée comme une autorisation de plus de la logistique, laisse un acheteur à
crédit recevoir une commande d'abord : le client de la commande a un compte de crédit actif, dans la devise de la
commande, dont le crédit disponible couvre ce qu'il reste à payer.

```php
$shipments->ship($order, $clock->now());                            // a buyer within the credit receives the order
$creditSales->charge($order, $order->customer(), $clock->now());    // owed on account when the application invoices
```

L'autorisation ne fait que répondre : ce que l'acheteur doit change quand `charge()` porte l'achat au compte. La
commande d'un invité, d'un acheteur sans compte ou dont le compte est suspendu, ou au-delà du crédit disponible,
attend ses paiements, ou est forcée par la logistique par quelqu'un qui en a le droit.

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_wholesale_credit_account` | `buyer` (clé primaire), `currency`, `credit_limit`, `terms_days`, `owed`, `active`, `version` |
| `phil_commerce_wholesale_receivable` | `id` (UUID v7), `buyer`, `order_number`, `payment`, `amount`, `currency`, `paid`, `credited`, `refund_due`, `issued_at`, `due_at` ; indexée par acheteur et par paiement |
