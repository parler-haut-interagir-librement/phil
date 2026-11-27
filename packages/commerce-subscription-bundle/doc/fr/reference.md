<!-- phil-dev source-sha256=39baf0c6e49172f317106fc6320218d22d0126f7a006d693bcd0dcbf32817339 -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommerceSubscriptionBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_subscription` ; `payment_attempts`, `abandon_pending_after_days`, `immediate_downgrades`, `gift_redeem_within_days`, `delivery_addresses` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Offer` | `final class`, entité Doctrine | Un palier d'une famille : prix, période, essai, catégorie de taxe, fonctionnalités, places |
| `Domain\Model\Subscription` | `final class`, entité Doctrine | L'abonnement d'un abonné : état, périodes, commande attendue, changement sur-le-champ, crédit reporté, payeur |
| `Domain\Model\Gift` | `final class`, entité Doctrine | Un abonnement offert à quelqu'un d'autre, échangé par un code |
| `Domain\Model\Seat` | `final class`, entité Doctrine | Un membre qui détient une place de l'abonnement d'une organisation |
| `Domain\Model\OrderAddress` | `final class`, entité Doctrine | L'adresse de livraison avec laquelle une commande a été passée, figée, et le moment où son envoi est parti |
| `Domain\ValueObject\DeliveryAddress` | `final readonly class` | Une adresse de `phil/address`, le mode de livraison, un numéro de téléphone en E.164 ou aucun |
| `Domain\ValueObject\BillingPeriod` | `final readonly class` | Un nombre d'une unité ; les fins de période comptées à partir d'un point d'ancrage |
| `Domain\ValueObject\ImmediateChange` | `final readonly class` | Un changement sur-le-champ qui attend sa commande : le palier, le temps qui reste, le crédit ; ce que le nouveau palier facture |
| `Domain\ValueObject\DunningStage` | `final readonly class` | À partir d'un certain nombre de jours de retard, toutes les fonctionnalités, quelques-unes ou aucune |
| `Domain\Dunning\DunningAccess` | énumération à valeurs chaînes | Complet, réduit, aucun |
| `Domain\ValueObject\StableIdentifier` | `final readonly class` | L'identifiant d'une personne ou d'une organisation qui ne change jamais ; refuse une adresse courriel |
| `Domain\Gift\GiftRenewal` | énumération adossée à une chaîne | L'acheteur paie les renouvellements, ou il se termine à son terme |
| `Domain\Gift\GiftState` | énumération adossée à une chaîne | En attente, payé, échangé, annulé |
| `Domain\Offer\BillingUnit` | énumération adossée à une chaîne | Jour, semaine, mois, année |
| `Domain\Subscription\SubscriptionState` | énumération adossée à une chaîne | En attente, à l'essai, actif, en souffrance, terminé |
| `Domain\Exception\SubscriptionRefused` | `final class`, `DomainError` | Un abonnement qui ne peut pas démarrer, se renouveler, changer ou se terminer comme demandé, une place refusée, ou une adresse de livraison sans le numéro de téléphone que son transporteur demande |
| `Domain\Exception\GiftRefused` | `final class`, `DomainError` | Un cadeau qui ne peut pas être acheté, payé, échangé ou annulé comme demandé |
| `Domain\Repository\OfferRepository` | interface | Trouve et enregistre les offres |
| `Domain\Repository\SubscriptionRepository` | interface | Trouve et enregistre les abonnements ; ceux de plusieurs abonnés d'un coup, ceux dont la période est dépassée, ceux en attente à commander, ceux en attente à abandonner |
| `Domain\Repository\GiftRepository` | interface | Trouve les cadeaux, par identifiant ou par l'empreinte SHA-256 de leur code, et les enregistre |
| `Domain\Repository\SeatRepository` | interface | Trouve, compte, enregistre et retire les places ; si un membre détient l'une des premières |
| `Application\Contract\OrganizationMembership` | interface | Les organisations auxquelles appartient un membre, implémenté par l'application |
| `Application\Contract\PhoneRequirement` | interface | Si le transporteur d'un mode de livraison demande un numéro de téléphone |
| `Application\Contract\DeliveryAddressRepository` | interface | Trouve et enregistre les copies dans les commandes ; efface les adresses d'un abonné ; purge celles qui dépassent une date limite |
| `Application\DeliveryAddresses` | `final readonly class` | Garde, retire, fige, note les envois, efface et purge les adresses de livraison |
| `Application\ConfiguredPhoneRequirement` | `final readonly class` | Les modes de livraison configurés comme demandant un numéro de téléphone |
| `Application\Message\PurgeDeliveryAddresses` | `final readonly class` | Le message de la purge planifiée, qui ne porte rien |
| `Application\Handler\PurgeDeliveryAddressesHandler` | `final readonly class` | Purge à l'heure de l'horloge |
| `Application\OfferSellables` | `final readonly class`, le `SellableResolver` du noyau | Les offres en vente sous le type `offer` ; la période suivante d'un abonnement sous `subscription` ; les périodes d'un cadeau sous `gift` |
| `Application\ProrationOrderProcessor` | `final readonly class`, l'`OrderProcessor` du noyau | Les ajustements `Proration` des lignes `subscription:<id>` |
| `Application\OrderedLine` | `final class` | Ce que vaut une ligne du module sur une commande, et les origines de son calcul au prorata |
| `Application\Subscriptions` | `final readonly class` | Abonne, commande, enregistre les paiements, annule, change de palier au renouvellement ou sur-le-champ, balaie |
| `Application\Gifts` | `final readonly class` | Achète, commande, paie, annule et échange les cadeaux |
| `Application\Seats` | `final readonly class` | Attribue et libère les places |
| `Application\FeatureAccess` | `final class`, étiquetée `kernel.reset` | Les fonctionnalités qu'un abonné ou un membre peut utiliser, lues une seule fois jusqu'à `forget()` ou `reset()` |
| `Infrastructure\Doctrine\DoctrineOfferRepository` | `final readonly class` | Les offres par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository` | `final readonly class` | Les abonnements par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrineGiftRepository` | `final readonly class` | Les cadeaux par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrineSeatRepository` | `final readonly class` | Les places par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrineDeliveryAddressRepository` | `final readonly class` | Les copies par le gestionnaire d'entités ; effacement et purge par des instructions DQL |
| `Infrastructure\Console\PurgeDeliveryAddressesCommand` | `final readonly class` | `phil:commerce-subscription:purge-addresses`, sous un verrou |
| `Infrastructure\Scheduler\DeliveryAddressSchedule` | `final class` | La planification `phil_commerce_subscription_addresses`, sous un verrou |
| `Infrastructure\Privacy\DeliveryAddressPrivacy` | `final readonly class`, l'exportateur et l'effaceur de privacy-bundle | Les adresses de livraison dans les demandes d'accès et d'effacement d'un abonné |

Voir [scope.md](scope.md) pour ce que le module laisse à d'autres paquets.
