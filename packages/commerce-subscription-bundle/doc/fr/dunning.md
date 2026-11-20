<!-- phil-dev source-sha256=11c2b3084c3bb8503526e5867f784152efe3f6974f3efea37f9d77ee5a22f744 -->
# La relance

Tant qu'un renouvellement est en souffrance — son paiement a échoué et est retenté —, les étapes de relance de l'offre
disent ce que l'abonné garde, à partir d'un certain nombre de jours de retard : toutes les fonctionnalités, quelques-unes
ou aucune. Une offre sans étapes garde toutes les fonctionnalités tant que durent les nouvelles tentatives, si bien
qu'une boutique qui ne règle rien ne coupe jamais personne par surprise.

```php
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DunningStage;

$offer->changeDunning([
    DunningStage::full(0),                     // the first days, everything
    DunningStage::reduced(3, ['read']),        // from the third day past due, reading only
    DunningStage::cut(10),                     // from the tenth day, nothing
]);
```

| Étape | Garde |
|---|---|
| `DunningStage::full($days)` | Toutes les fonctionnalités du palier |
| `DunningStage::reduced($days, $features)` | Les fonctionnalités nommées, une ou plus, chacune une fonctionnalité du palier |
| `DunningStage::cut($days)` | Aucune fonctionnalité |

- **Les jours sont des jours entiers depuis le premier échec** du renouvellement, de 0 à 365 ; chaque étape commence
  plus tard que la précédente, au plus dix étapes par offre. Avant la première étape, toutes les fonctionnalités sont
  gardées.
- **Le premier échec lance le compte** : `Subscription::pastDueSince()` est le moment du premier paiement échoué du
  renouvellement, et `daysPastDue($at)` les jours entiers depuis ; une période payée l'efface, et l'échec suivant lance
  un nouveau compte.
- **Les nouvelles tentatives et la fin ne changent pas** : l'abonnement reste en souffrance jusqu'à ce qu'un paiement
  réussisse ou que la dernière tentative de `payment_attempts` échoue, puis il se termine ; les étapes ne décident que
  de ce que `FeatureAccess` accorde entre-temps.
- **`FeatureAccess` lit le moment sur l'horloge** — le service `clock` du conteneur quand il y en a un, l'heure du
  système sinon — et garde les fonctionnalités d'un abonné jusqu'à une remise à zéro, comme pour tout autre changement
  ([features.md](features.md)) ; un *worker* de longue durée la remet à zéro pour chaque requête ou message.
- **L'abonnement en souffrance d'une organisation** donne à ses membres ce que gardent ses étapes, de la même façon.

Les étapes sont stockées avec l'offre, dans la colonne JSON `dunning` ; le moment du premier échec, dans la colonne
`past_due_since` de l'abonnement.
