<!-- phil-dev source-sha256=3ba605ef4017a1df1d90dea877cc9f938c418a021608023b820194647dbcf9b0 -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application configure ou utilise. Les ports du
noyau et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette |
|---|---|---|
| `SellableResolver` | `OfferSellables`, pour les types de référence `offer`, `subscription` et `gift` | `phil_commerce.sellable_resolver`, par l'autoconfiguration du noyau |
| `OrderProcessor` | `ProrationOrderProcessor`, les ajustements `Proration` d'un changement sur-le-champ et du crédit reporté | `phil_commerce.order_processor`, après `OptionSupplementProcessor` et les promotions du catalogue, avant `TaxOrderProcessor` |

Les prix viennent des résolveurs, chaque montant est un `Money` dans la devise de la commande, et les taxes sont
calculées par le noyau sur chaque ligne. Voir [Portée](scope.md).

## Fonctionnalités

Les fonctionnalités qu'énumère une offre portent les noms de l'application : `FeatureAccess::features()` et `grants()`
disent ce qu'un abonné peut utiliser, et un indicateur de fonctionnalité qui dépend d'un palier lit `grants()`.
`FeatureAccess::forget()` oublie ce qu'il a lu d'un abonné dont l'abonnement a changé hors de `Subscriptions`, ou d'un
membre qui a rejoint ou quitté une organisation. Voir [Fonctionnalités](features.md).

## Organisations

L'application implémente `Application\Contract\OrganizationMembership` : `organizationsOf(member)` retourne les
identifiants stables des organisations auxquelles appartient un membre. Déclaré comme service, c'est celui que reçoit
`FeatureAccess` ; sans lui, aucune organisation n'ouvre ses fonctionnalités. Voir [Organisations](organizations.md).

## Dépôts

`OfferRepository`, `SubscriptionRepository`, `GiftRepository` et `SeatRepository` sont des alias publics de leurs
adaptateurs Doctrine ; un stockage propre à l'application garde `findByCodes()` et `forSubscribers()` capables de lire
plusieurs rangées d'un coup, les balayages — `periodOverBy()`, `pendingWithoutOrder()`, `pendingCreatedBefore()` —
bornés par leur limite, `findByCodeHash()` sur un index, et `holdsSeat()` qui compte les places attribuées avant celle
du membre plutôt que de les lire.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `payment_attempts` | Combien de paiements échoués d'un renouvellement mettent fin à un abonnement |
| `abandon_pending_after_days` | Combien de jours une première période impayée reste en attente avant que le balayage y mette fin |
| `immediate_downgrades` | Si un changement sur-le-champ vers un palier inférieur est calculé au prorata maintenant plutôt qu'au renouvellement suivant |
| `gift_redeem_within_days` | Combien de jours le code d'un cadeau permet de l'échanger |

Voir [Configuration](configuration.md).

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Subscriptions` | Abonne, renouvelle, enregistre les paiements et les échecs, annule, change de palier, balaie ; public — voir [Abonnements](subscriptions.md) et [Changer de palier sur-le-champ](proration.md) |
| `Gifts` | Les cadeaux achetés, payés et échangés par un code ; public — voir [Cadeaux](gifts.md) |
| `Seats` | Les places de l'abonnement d'une organisation ; public — voir [Organisations](organizations.md) |
| `FeatureAccess` | Ce qu'un abonné ou un membre peut utiliser ; public, remis à zéro entre les requêtes |
