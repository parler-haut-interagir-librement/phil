<!-- phil-dev source-sha256=9788ee325786a811ee171e0dbc20656f70afb40ac22871596dc26d5b28273c90 -->
# Versements

Le bundle **ne déplace aucun argent**. L'application paie ses consignataires — un virement, un chèque, de l'argent
comptant, un crédit en magasin — et écrit ici ce qu'elle a versé ; le bundle garde ce qui est dû, ce qui a été versé et
le solde entre les deux.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;

$payout = $payouts->record(
    idempotencyKey: $transfer->id,
    consignor: Owner::consignor('author-17'),
    amount: $statement->share(),
    paidAt: $transfer->sentAt,
    method: 'transfer',
    reference: $transfer->reference,
    at: $clock->now(),
);
$entityManager->flush();
```

| Champ | Ce qu'il contient |
|---|---|
| `idempotencyKey` | Une clé que l'application donne au versement — l'identifiant du virement, du chèque, du formulaire qui l'a envoyé —, de 1 à 64 caractères, unique. |
| `amount` | Ce qui a été versé, plus que zéro, en `Money` : au plus le solde dû dans sa devise. |
| `paidAt` | Quand il a été versé, en UTC. |
| `method` | Comment il a été versé, comme l'application le nomme : de 1 à 32 lettres minuscules, chiffres, points, tirets ou traits de soulignement — `transfer`, `cheque`, `cash`, `store_credit`. |
| `reference` | Une référence libre, jusqu'à 64 caractères : celle du virement, le numéro du chèque ; ou `null`. |

Un versement peut être partiel : le solde garde le reste.

## Le même versement deux fois

Un versement dont la clé est déjà écrite est refusé : le même versement écrit deux fois par erreur, un formulaire
renvoyé. La clé est aussi unique dans la table, si bien que deux requêtes qui écrivent le même versement en même temps
ne peuvent pas toutes les deux valider : le second `flush` lève l'`UniqueConstraintViolationException` de Doctrine, et
rien n'en est écrit.

## Corrections

Un versement n'est jamais modifié ni supprimé. Un versement écrit par erreur est annulé par sa **contrepassation** :

```php
$payouts->reverse($payout->id, idempotencyKey: $payout->idempotencyKey.'-reversal', at: $clock->now());
```

La contrepassation est un versement du montant opposé, par la même méthode et la même référence, qui nomme le versement
qu'elle annule. Un versement n'est contrepassé qu'une fois — la table refuse une seconde contrepassation, même de deux
requêtes en même temps —, et une contrepassation n'est jamais contrepassée : on écrit plutôt de nouveau le bon
versement.

## Le solde

```php
$balance = $payouts->balance(Owner::consignor('author-17'), Currency::of('CAD'));

$balance->owed;     // from the ledger: the shares of the units sold and bought out, less those brought back
$balance->paid;     // the payouts, reversals deducted
$balance->balance;  // owed less paid
$balance->state;    // PayoutState::Due, PartlyPaid or Paid
```

| État | Quand |
|---|---|
| `Due` | Quelque chose est dû et rien n'a été versé. |
| `PartlyPaid` | Quelque chose a été versé, et quelque chose est encore dû. |
| `Paid` | Rien n'est dû : tout a été versé, ou rien n'a jamais été dû. |

Ce qui est dû est lu dans tout le grand livre, dans une devise, comme les relevés ; il n'attend pas un relevé. Le retour
d'un client après un versement peut rendre le solde négatif : le consignataire doit alors de l'argent à la boutique, et
l'état est `Paid`. Le bundle ne décide pas comment cela se règle — déduit du versement suivant, remboursé — : les
versements suivants sont plus petits, ou l'application écrit ce qui a été remboursé comme elle l'entend.

Les sommes comptent les écritures et les versements que cette requête a écrits et n'a pas encore validés par `flush` :
une vente et un versement d'une même requête se voient.
