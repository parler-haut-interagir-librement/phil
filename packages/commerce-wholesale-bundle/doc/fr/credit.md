<!-- phil-dev source-sha256=69427cd5fce83205f4093fbf6c0fecf74b3311f1b8cb23c1d1080a9003a45570 -->
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

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_wholesale_credit_account` | `buyer` (clé primaire), `currency`, `credit_limit`, `terms_days`, `owed`, `active`, `version` |
| `phil_commerce_wholesale_receivable` | `id` (UUID v7), `buyer`, `order_number`, `payment`, `amount`, `currency`, `paid`, `credited`, `refund_due`, `issued_at`, `due_at` ; indexée par acheteur et par paiement |
