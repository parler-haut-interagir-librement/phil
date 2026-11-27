<!-- phil-dev source-sha256=27b9e28e50d78ec58bfb44a4102c543b756a1ac581728139466d65df40b752b9 -->
# Configuration

L'alias de l'extension est `phil_commerce_subscription`.

```yaml
# config/packages/phil_commerce_subscription.yaml
phil_commerce_subscription:
    payment_attempts: 3             # the default
    abandon_pending_after_days: 7   # the default
    immediate_downgrades: false     # the default
    gift_redeem_within_days: 365    # the default
    delivery_addresses:
        retention_days: 90          # the default
        phone_required_methods: []  # the default
        purge_batch: 500            # the default
        schedule:
            enabled: true           # the default, with symfony/scheduler
            frequency: '1 day'      # the default
        lock_factory: lock.factory  # the default; null for none
```

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `payment_attempts` | entier, de 1 à 10 | `3` | Combien de paiements échoués d'un renouvellement mettent fin à un abonnement ; jusque-là, il reste en souffrance, avec accès |
| `abandon_pending_after_days` | entier, de 1 à 365 | `7` | Combien de jours un abonnement dont la première période n'a jamais été payée reste en attente avant que `sweep()` y mette fin |
| `immediate_downgrades` | booléen | `false` | Si `changeNow()` vers un palier inférieur prend effet sur-le-champ, au prorata, plutôt qu'au renouvellement suivant |
| `gift_redeem_within_days` | entier, de 1 à 3650 | `365` | Combien de jours le code d'un cadeau payé permet de l'échanger |
| `delivery_addresses.retention_days` | entier, de 1 à 3650 | `90` | Combien de jours après le dernier envoi une adresse de livraison est effacée : à la fin de l'abonnement pour la sienne, au départ de l'envoi pour une copie |
| `delivery_addresses.phone_required_methods` | liste de chaînes | `[]` | Les modes de livraison dont le transporteur demande un numéro de téléphone ; tout autre mode n'en garde aucun |
| `delivery_addresses.purge_batch` | entier, de 1 à 10000 | `500` | Combien d'adresses et de copies chaque lot d'une purge efface |
| `delivery_addresses.schedule.enabled` | booléen | `true` | Si la purge récurrente est planifiée, avec `symfony/scheduler` |
| `delivery_addresses.schedule.frequency` | chaîne | `'1 day'` | À quelle fréquence la purge s'exécute, comme `RecurringMessage::every()` la lit |
| `delivery_addresses.lock_factory` | identifiant de service ou `null` | `'lock.factory'` | Le `LockFactory` sous lequel s'exécutent la purge planifiée et la commande, quand le service existe ; `null` pour aucun |

## Ce que le module enregistre

| Service | Rôle |
|---|---|
| `Application\OfferSellables` | Vend au noyau les offres en vente sous le type de référence `offer`, la période suivante d'un abonnement sous `subscription`, et les périodes d'un cadeau sous `gift` ; étiqueté par l'autoconfiguration du noyau |
| `Application\ProrationOrderProcessor` | Calcule au prorata les lignes `subscription:<id>` ; étiqueté `phil_commerce.order_processor` après les options et les promotions, avant les taxes |
| `Application\Subscriptions` (public) | Abonne, commande, enregistre les paiements, annule, change de palier au renouvellement ou sur-le-champ, balaie ; informe `FeatureAccess` de chaque changement |
| `Application\Gifts` (public) | Achète, commande, paie, annule et échange les cadeaux |
| `Application\Seats` (public) | Attribue et libère les places de l'abonnement d'une organisation |
| `Application\FeatureAccess` (public, étiqueté `kernel.reset`) | Les fonctionnalités qu'un abonné peut utiliser, lues une seule fois par abonné jusqu'à un changement ou une remise à zéro ; reçoit l'`OrganizationMembership` de l'application quand elle en déclare un, et l'horloge `clock` du conteneur pour les étapes de relance |
| `Application\DeliveryAddresses` (public) | Les adresses de livraison des abonnements et leurs copies dans chaque commande, leur effacement et leur purge ; donné à `Subscriptions`, qui fige l'adresse de chaque commande qu'il passe |
| `Application\ConfiguredPhoneRequirement`, avec l'alias `PhoneRequirement` | Les modes de livraison de `phone_required_methods` |
| `Application\Handler\PurgeDeliveryAddressesHandler` | Traite `PurgeDeliveryAddresses`, étiqueté `messenger.message_handler`, à l'heure de l'horloge `clock` du conteneur |
| `Infrastructure\Console\PurgeDeliveryAddressesCommand` | `phil:commerce-subscription:purge-addresses`, avec `symfony/console` |
| `Infrastructure\Scheduler\DeliveryAddressSchedule` | La planification `phil_commerce_subscription_addresses`, avec `symfony/scheduler`, sous le verrou quand il existe |
| `Infrastructure\Privacy\DeliveryAddressPrivacy` | Étiqueté `phil_privacy.exporter` et `phil_privacy.eraser`, avec `phil/privacy-bundle` enregistré |
| `Infrastructure\Doctrine\DoctrineOfferRepository`, avec l'alias (public) `OfferRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository`, avec l'alias (public) `SubscriptionRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineGiftRepository`, avec l'alias (public) `GiftRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineSeatRepository`, avec l'alias (public) `SeatRepository` | Avec DoctrineBundle seulement |
| `Infrastructure\Doctrine\DoctrineDeliveryAddressRepository`, avec l'alias (public) `DeliveryAddressRepository` | Avec DoctrineBundle seulement |

Avec DoctrineBundle, le module ajoute en tête la correspondance par attributs de `Domain\Model`. Générez une migration
pour les tables `phil_commerce_subscription_offer`, `phil_commerce_subscription`, `phil_commerce_subscription_gift`,
`phil_commerce_subscription_seat` et `phil_commerce_subscription_order_address`. Les colonnes `delivery_address` et
`last_shipped_at` de `phil_commerce_subscription` acceptent `NULL` : la migration qui les ajoute à une table existante
laisse donc ses rangées telles quelles. Sans DoctrineBundle, chaque service lit un dépôt : l'application les déclare
elle-même, avec ses propres adaptateurs.
