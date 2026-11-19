<!-- phil-dev source-sha256=9f640e9088803b1146ce034d495d2fd7a009d12b0582e02defa7eea29d271e10 -->
<!-- phil-dev source-sha256=0 -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application implémente, configure ou utilise. Les
ports du noyau et leurs contrats y sont décrits ; les messages sont signés en Standard Webhooks par
`phil/standard-webhooks`.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette |
|---|---|---|
| `SellableResolver` | `SyndicatedSellables`, pour le type `syndicated` | `phil_commerce.sellable_resolver` |
| `SellableAvailability` | `SyndicatedSellables` : un produit retiré ou en rupture de stock est caché dans tous les canaux, et donc refusé | `phil_commerce.sellable_availability` |

Voir [Portée](scope.md).

## Ses propres ports

### `ForwardedOrderHandler`

`Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler` — ce que fait un fournisseur d'une commande
qu'un revendeur lui a transmise. Un service qui l'implémente reçoit l'étiquette
`phil_commerce_syndication.forwarded_order_handler` par autoconfiguration.

| Méthode | Contrat |
|---|---|
| `handle(ForwardedOrder $order): void` | Prépare le plus souvent l'expédition de la commande ; s'exécute une fois par commande, dans la transaction qui enregistre le message |

### `PartnerShipmentHandler`

`Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler` — ce que fait un revendeur une fois que son
fournisseur a expédié un colis d'une commande transmise. Un service qui l'implémente reçoit l'étiquette
`phil_commerce_syndication.shipment_handler` par autoconfiguration.

| Méthode | Contrat |
|---|---|
| `shipped(PartnerShipment $shipment): void` | Marque la commande expédiée, avertit l'acheteur ; s'exécute une fois par colis, dans la transaction qui enregistre le message |

### `PartnerReturnHandler`

`Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler` — ce que fait chaque côté quand un retour
circule. Un service qui l'implémente reçoit l'étiquette `phil_commerce_syndication.return_handler` par
autoconfiguration.

| Méthode | Contrat |
|---|---|
| `requested(PartnerReturn $return): void` | Chez le fournisseur : un revendeur a annoncé le retour ; s'exécute une fois par retour |
| `received(PartnerReturn $return): void` | Chez le revendeur : le fournisseur l'a reçu et crédité ; s'exécute une fois par retour |

### `MessageTransport`

`Phil\CommerceSyndicationBundle\Application\Port\MessageTransport` — transporte un message signé jusqu'à un
partenaire. `HttpClientTransport` publie par le client HTTP de Symfony ; une application fait pointer le port vers le
sien par un alias.

| Méthode | Contrat |
|---|---|
| `send(string $url, array $headers, string $body): int` | Publie le corps avec ses en-têtes tels quels et retourne le statut HTTP ; `0` quand aucune réponse n'est venue — le point de terminaison injoignable, la connexion coupée, le délai expiré |

## Dépôts

`PartnerRepository`, `PublishedPriceRepository`, `LedgerRepository`, `MessageRepository`,
`SyndicatedProductRepository`, `PartnerShipmentRepository` et `PartnerReturnRepository` sont des alias publics de
`DoctrineSyndicationRepository`. `LedgerRepository::findEntry()` donne l'écriture de la commande elle-même, jamais celle
d'un retour, et `entriesOf()` les donne toutes. Un stockage propre à l'application garde `MessageRepository::claim()` atomique, pour que deux livraisons n'envoient jamais un même message,
et `due()` borné par sa limite.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `identity` | Le nom de cette application chez ses partenaires |
| `signing_key` | Sa clé secrète Ed25519 `whsk_` |
| `tolerance` | De combien l'horodatage d'un message peut s'écarter |
| `max_attempts`, `retention_days` | Les livraisons, et la durée de conservation des messages |
| `cost_grace_days` | Combien de temps un coût remplacé paie encore une ligne transmise |

## Services et commandes

`Partners`, `Publisher`, `Forwarder`, `Ledger`, `ShipmentTracking` et `PartnerReturns` sont publics ;
`phil:commerce-syndication:deliver` et `phil:commerce-syndication:purge` s'exécutent depuis un cron ou un planificateur.
Voir [Syndication](syndication.md) et [Messages](messages.md).
