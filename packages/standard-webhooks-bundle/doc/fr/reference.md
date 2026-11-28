<!-- phil-dev source-sha256=5021221995989c1c39fa89e217a3c9e9d7be5585dee3af7f122abf8cf21f8ecb -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilStandardWebhooksBundle` | `final class`, `AbstractBundle` | Alias `phil_standard_webhooks` ; la configuration et les services |
| `Application\SigningKeys` | `final readonly class` | Les clés de signature configurées : la courante, et les autres d'une rotation |
| `Application\Library` | `final class` | Les types de la bibliothèque qu'enregistre le bundle, par nom |
| `Infrastructure\Http\VerifiedWebhookResolver` | résolveur d'arguments de contrôleur | Un argument `VerifiedWebhook`, vérifié ; `413`, `400` ou `401` ; le corps borné avant d'être lu en entier ; le récepteur construit seulement pour un tel argument |
| `Infrastructure\Http\WebhookSender` | service | Une tentative signée par le client HTTP de Symfony, vers des URL `https` hors des réseaux privés, sauf permission |
| `Infrastructure\Http\DeliveryFailed` | exception, une `WebhookException` | Une livraison non confirmée ou refusée : son identifiant, l'état s'il y en a un, si elle est `retryable`, et la `cause`, la classe d'une erreur de transport |
| `Infrastructure\Doctrine\DbalProcessedWebhooks` | `ProcessedWebhookRepository` | Les identifiants traités d'un récepteur dans leur table ; `forReceiver()`, `purge()` |
| `Infrastructure\Doctrine\ProcessedWebhookTable` | schéma | `phil_standard_webhooks_processed`, à clé le récepteur et l'identifiant |
| `Infrastructure\Doctrine\ProcessedWebhookSchemaListener` | écouteur Doctrine | La table dans le schéma de l'ORM |
| `Infrastructure\Console\GenerateKeyCommand` | commande console | `phil:standard-webhooks:generate-key` |

Les types du protocole — `WebhookReceiver`, `VerifiedWebhook`, les clés, les exceptions — sont ceux de
`phil/standard-webhooks` : voyez sa référence (`doc/reference.md` de `phil/standard-webhooks`).
