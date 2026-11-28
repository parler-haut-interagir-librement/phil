<!-- phil-dev source-sha256=1ddd754ebf087fb4b84c3696860a31892476709808d9e9b48b96e85ef6a79a3f -->
# Tableau d'échange

Un bénévole qui occupe une place et ne peut pas venir l'offre sur le tableau d'échange, avec une note de 500 caractères
au plus. Un autre bénévole la prend. La place passe tout de suite, ou, quand le quart a `swapApproval`, une fois qu'un
coordonnateur approuve. Une offre est immuable : chaque étape retourne la suivante.

```php
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;

$offer = SwapOffer::offer($id, $roster, 'ada', 'Sick, sorry', $now);       // Open
$offer = $offer->take($roster, 'cy', $rolesOfCy, $now);                     // Completed, or Pending

if (SwapStatus::Pending === $offer->status) {
    $offer = $offer->approve($roster, 'grace', $now);                       // Completed
}

$roster = $offer->apply($roster, $now);                                     // the place is cy's
```

## Les états

| État | Signifie | Ensuite |
|---|---|---|
| `Open` | sur le tableau, pour tout bénévole | `take()`, `cancel()` |
| `Pending` | prise, en attente d'un coordonnateur | `approve()`, `refuse()`, `cancel()` |
| `Completed` | la place passe — `apply()` donne la liste | — |
| `Cancelled` | retirée par le bénévole qui l'avait offerte | — |

Un coordonnateur qui refuse remet l'offre sur le tableau : elle est de nouveau ouverte, avec le coordonnateur et
l'instant dans `decidedBy` et `decidedAt`.

## Qui peut faire quoi

- **Offrir** : un bénévole qui occupe une place d'un quart ouvert.
- **Prendre** : un autre bénévole, avec le rôle qu'exige le quart, qui n'occupe aucune place sur celui-ci — un bénévole
  de la réserve ou des intéressés le peut.
- **Approuver** et **refuser** : un coordonnateur ; la bibliothèque prend son identifiant, et l'application décide qui en
  est un.
- **Annuler** : le bénévole qui a offert la place, tant que l'offre est ouverte ou en attente.

Chaque étape lit la liste : l'offre d'un autre quart est refusée avec `swap.other_shift`, et une offre dont le bénévole
n'occupe plus la place — désisté, ou retiré par un coordonnateur — est **périmée** et refusée avec `swap.stale` ;
`isStale()` indique à un écran quelles offres masquer. Une fois le quart fermé, rien ne bouge : `shift.closed`.

| Raison | Quand |
|---|---|
| `swap.not_open` | prendre ou annuler une offre qui n'est plus ouverte |
| `swap.not_pending` | approuver ou refuser une offre qui n'attend personne, ou appliquer une offre non terminée |
| `swap.own_offer` | le bénévole qui a offert la place la prend |
| `swap.not_yours` | un autre bénévole l'annule |
| `swap.other_shift` | la liste donnée est celle d'un autre quart |
| `swap.stale` | le bénévole qui a offert la place ne l'occupe plus |

Les conflits du bénévole qui prend une place sont à vérifier par l'application, avec `Conflicts`, comme pour une
inscription.
