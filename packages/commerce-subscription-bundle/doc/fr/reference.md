<!-- phil-dev source-sha256=cbcca151d3953ec5189fa36e1611f218553c885c897fb9269f7e0fccff1e290f -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommerceSubscriptionBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_subscription` ; `payment_attempts`, `abandon_pending_after_days`, `immediate_downgrades`, `gift_redeem_within_days` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Offer` | `final class`, entité Doctrine | Un palier d'une famille : prix, période, essai, catégorie de taxe, fonctionnalités, places |
| `Domain\Model\Subscription` | `final class`, entité Doctrine | L'abonnement d'un abonné : état, périodes, commande attendue, changement sur-le-champ, crédit reporté, payeur |
| `Domain\Model\Gift` | `final class`, entité Doctrine | Un abonnement offert à quelqu'un d'autre, échangé par un code |
| `Domain\Model\Seat` | `final class`, entité Doctrine | Un membre qui détient une place de l'abonnement d'une organisation |
| `Domain\ValueObject\BillingPeriod` | `final readonly class` | Un nombre d'une unité ; les fins de période comptées à partir d'un point d'ancrage |
| `Domain\ValueObject\ImmediateChange` | `final readonly class` | Un changement sur-le-champ qui attend sa commande : le palier, le temps qui reste, le crédit ; ce que le nouveau palier facture |
| `Domain\ValueObject\DunningStage` | `final readonly class` | À partir d'un certain nombre de jours de retard, toutes les fonctionnalités, quelques-unes ou aucune |
| `Domain\Dunning\DunningAccess` | énumération à valeurs chaînes | Complet, réduit, aucun |
| `Domain\ValueObject\StableIdentifier` | `final readonly class` | L'identifiant d'une personne ou d'une organisation qui ne change jamais ; refuse une adresse courriel |
| `Domain\Gift\GiftRenewal` | énumération adossée à une chaîne | L'acheteur paie les renouvellements, ou il se termine à son terme |
| `Domain\Gift\GiftState` | énumération adossée à une chaîne | En attente, payé, échangé, annulé |
| `Domain\Offer\BillingUnit` | énumération adossée à une chaîne | Jour, semaine, mois, année |
| `Domain\Subscription\SubscriptionState` | énumération adossée à une chaîne | En attente, à l'essai, actif, en souffrance, terminé |
| `Domain\Exception\SubscriptionRefused` | `final class`, `DomainError` | Un abonnement qui ne peut pas démarrer, se renouveler, changer ou se terminer comme demandé, ou une place refusée |
| `Domain\Exception\GiftRefused` | `final class`, `DomainError` | Un cadeau qui ne peut pas être acheté, payé, échangé ou annulé comme demandé |
| `Domain\Repository\OfferRepository` | interface | Trouve et enregistre les offres |
| `Domain\Repository\SubscriptionRepository` | interface | Trouve et enregistre les abonnements ; ceux de plusieurs abonnés d'un coup, ceux dont la période est dépassée, ceux en attente à commander, ceux en attente à abandonner |
| `Domain\Repository\GiftRepository` | interface | Trouve les cadeaux, par identifiant ou par l'empreinte SHA-256 de leur code, et les enregistre |
| `Domain\Repository\SeatRepository` | interface | Trouve, compte, enregistre et retire les places ; si un membre détient l'une des premières |
| `Application\Contract\OrganizationMembership` | interface | Les organisations auxquelles appartient un membre, implémenté par l'application |
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

Voir [scope.md](scope.md) pour ce que le module laisse à d'autres paquets.
