<!-- phil-dev source-sha256=f2ce3ed9a5747ac24aae145f36730746b296143429daa99ddc434e5bd7a8013e -->
# Configuration

L'alias de l'extension est `phil_commerce_subscription`.

```yaml
# config/packages/phil_commerce_subscription.yaml
phil_commerce_subscription:
    payment_attempts: 3             # the default
    abandon_pending_after_days: 7   # the default
    immediate_downgrades: false     # the default
    gift_redeem_within_days: 365    # the default
```

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `payment_attempts` | entier, de 1 à 10 | `3` | Combien de paiements échoués d'un renouvellement mettent fin à un abonnement ; jusque-là, il reste en souffrance, avec accès |
| `abandon_pending_after_days` | entier, de 1 à 365 | `7` | Combien de jours un abonnement dont la première période n'a jamais été payée reste en attente avant que `sweep()` y mette fin |
| `immediate_downgrades` | booléen | `false` | Si `changeNow()` vers un palier inférieur prend effet sur-le-champ, au prorata, plutôt qu'au renouvellement suivant |
| `gift_redeem_within_days` | entier, de 1 à 3650 | `365` | Combien de jours le code d'un cadeau payé permet de l'échanger |

## Ce que le module enregistre

| Service | Rôle |
|---|---|
| `Application\OfferSellables` | Vend au noyau les offres en vente sous le type de référence `offer`, la période suivante d'un abonnement sous `subscription`, et les périodes d'un cadeau sous `gift` ; étiqueté par l'autoconfiguration du noyau |
| `Application\ProrationOrderProcessor` | Calcule au prorata les lignes `subscription:<id>` ; étiqueté `phil_commerce.order_processor` après les options et les promotions, avant les taxes |
| `Application\Subscriptions` (public) | Abonne, commande, enregistre les paiements, annule, change de palier au renouvellement ou sur-le-champ, balaie ; informe `FeatureAccess` de chaque changement |
| `Application\Gifts` (public) | Achète, commande, paie, annule et échange les cadeaux |
| `Application\Seats` (public) | Attribue et libère les places de l'abonnement d'une organisation |
| `Application\FeatureAccess` (public, étiqueté `kernel.reset`) | Les fonctionnalités qu'un abonné peut utiliser, lues une seule fois par abonné jusqu'à un changement ou une remise à zéro ; reçoit l'`OrganizationMembership` de l'application quand elle en déclare un, et l'horloge `clock` du conteneur pour les étapes de relance |
| `Infrastructure\Doctrine\DoctrineOfferRepository`, avec l'alias (public) `OfferRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository`, avec l'alias (public) `SubscriptionRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineGiftRepository`, avec l'alias (public) `GiftRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineSeatRepository`, avec l'alias (public) `SeatRepository` | Avec DoctrineBundle seulement |

Avec DoctrineBundle, le module ajoute en tête la correspondance par attributs de `Domain\Model`. Générez une migration
pour les tables `phil_commerce_subscription_offer`, `phil_commerce_subscription`, `phil_commerce_subscription_gift` et
`phil_commerce_subscription_seat`.
