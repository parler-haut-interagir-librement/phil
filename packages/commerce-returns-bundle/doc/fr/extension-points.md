<!-- phil-dev source-sha256=9051014ec9385760736958a532357ebeca8de47f276659629005c599a9f9c60c -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application implémente, configure ou utilise. Les
ports du noyau et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette |
|---|---|---|
| `PaymentGateway` | `ReturnsGateway`, pour la méthode `exchange` | `phil_commerce.payment_gateway` |
| `DocumentTypes` | `ReturnDocumentTypes`, la série `return_slip` | `phil_commerce.document_types`, par autoconfiguration |

Voir [Portée](scope.md).

## Ses propres ports

### `Restocking`

`Phil\CommerceReturnsBundle\Application\Port\Restocking` — remet sur la tablette ce qui est revenu en état d'être
revendu. Un service qui l'implémente reçoit l'étiquette `phil_commerce_returns.restocking` par autoconfiguration ;
chacun s'exécute à la réception d'un retour, dans la transaction qui l'enregistre.

| Méthode | Contrat |
|---|---|
| `restock(SellableReference $line, int $quantity, string $return, DateTimeImmutable $at): void` | Remet les unités en place — un mouvement de stock de `phil/commerce-logistics-bundle`, ou celui de l'application ; `$line` est la référence de la ligne de commande, et ce qui retourne sur la tablette est son vendable `unconfigured()` |

`Infrastructure\Logistics\LogisticsRestocking` est celui du module, sur `phil/commerce-logistics-bundle`, enregistré et
étiqueté quand `logistics_restocking` est activé ; une application qui remet en stock autrement le laisse désactivé, pour
qu'aucune unité ne revienne deux fois.

### `StoreCreditGranter`

`Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter` — donne à un client un crédit en magasin pour un
retour. `StoredValueStoreCredit` utilise `phil/commerce-stored-value-bundle` quand il est enregistré ; sinon, une
application fait pointer le port vers le sien par un alias.

| Méthode | Contrat |
|---|---|
| `grant(string $customer, Money $amount, DateTimeImmutable $at, string $note, string $orderNumber): void` | Crédite le client du montant, dans la devise de la commande |

### `SetParts`, `SpecialDiscounts`, `ShipmentDates`

`Phil\CommerceReturnsBundle\Application\Contract\…` — ce que d'autres modules savent d'une commande. Chacun est servi par
un adaptateur que le module enregistre quand le module qui sait l'est lui-même ; sinon, une application fait pointer
le port vers son propre service par un alias.

| Port | Méthode | Adaptateur |
|---|---|---|
| `SetParts` | `partsOf(SellableReference $reference): list<string>` — les parties qu'une unité d'un ensemble contient, comme les lignes de commande les nommeraient | `CatalogSetParts`, sur `phil/commerce-catalog-bundle` |
| `SpecialDiscounts` | `isSpecial(Adjustment $adjustment): bool` — si un ajustement de promotion est celui d'une promotion spéciale | `PromotionSpecials`, sur `phil/commerce-promotions-bundle` |
| `ShipmentDates` | `lastShippedAt(string $orderNumber): ?DateTimeImmutable` | `LogisticsShipmentDates`, sur `phil/commerce-logistics-bundle` |

### `ReturnLabels`

`Phil\CommerceReturnsBundle\Application\Contract\ReturnLabels` — demande à un transporteur l'étiquette d'un retour
autorisé. L'application l'implémente — un adaptateur par transporteur — et fait pointer le port vers lui par un alias ;
`Returns::label()` est refusé sans adaptateur.

| Méthode | Contrat |
|---|---|
| `labelFor(ReturnRequest $return, DateTimeImmutable $at): ReturnLabel` | Le transporteur, le numéro de suivi, et la référence propre à l'adaptateur vers le fichier de l'étiquette |

### `ReturnSlipRenderer`

`Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer` — produit un bordereau en PDF.
`PagedMediaReturnSlips` le sert quand `phil/paged-media` est installé ; une application fait pointer le port vers son
propre moteur de rendu pour produire son propre bordereau.

| Méthode | Contrat |
|---|---|
| `render(ReturnSlip $slip, ?string $locale = null): string` | Les octets du PDF, dans la locale donnée |

## Dépôts

`ReturnRepository` est un alias public de `DoctrineReturnRepository` ; une application le fait pointer vers un service
à elle qui respecte le même contrat.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `window_days` | Combien de jours après qu'une commande est passée — ou expédiée pour la dernière fois — sa marchandise peut revenir ; 30 par défaut |
| `window_from` | `placed`, la valeur par défaut, ou `shipped` : la dernière expédition de la commande, quand le module de logistique en connaît une |
| `restocking_fee` | La part retenue sur les lignes dont la boutique n'est pas responsable, en parties par million |
| `delivery_refund` | `never`, `shops_fault` (la valeur par défaut) ou `whole_order` : quand les frais de livraison sont rendus |
| `expiry_days` | Combien de jours un retour autorisé attend sa marchandise avant que `expire()` le fasse expirer ; `null`, la valeur par défaut, pour toujours |
| `rate_limiter` | Le service d'une fabrique de `symfony/rate-limiter` qui borne les retours que chaque client demande ; `null`, la valeur par défaut, pour aucune |
| `logistics_restocking.enabled` | Remet les unités revendables dans le stock de `phil/commerce-logistics-bundle`, qui doit être enregistré ; désactivé par défaut |
| `logistics_restocking.location` | Le code de l'emplacement où va chaque retour, de 1 à 64 caractères ; `null`, la valeur par défaut, pour celui d'où chaque *SKU* est parti |
| `slips.prefix`, `slips.digits` | Le préfixe et les chiffres des numéros de bordereau ; `RS-` et 8 par défaut |

Une limite de débit compte dans le stockage que nomme sa fabrique : derrière un répartiteur de charge, un stockage
partagé par tous les serveurs, pour qu'un client ne puisse pas dépenser la limite une fois par serveur.

## Événements

`ReturnStateChanged`, `ReturnSettled` et `ReturnCreditNoteIssued` sont émis par leur classe par `event_dispatcher`,
quand il y en a un : une application les écoute avec `#[AsEventListener]` ou l'étiquette `kernel.event_listener` —
voyez [Retours](returns.md#événements).

## Traductions

Les mots du bordereau sont les clés `phil_commerce_returns.slip.*` et `phil_commerce_returns.reason.*` du domaine
`phil_commerce_returns`, traduites dans `translations/phil_commerce_returns.en.php` et `.fr.php` ; une application les
remplace dans ses propres traductions.

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Returns` | Les demandes, les étapes, le règlement, l'expiration, les étiquettes, l'anonymisation ; public — voyez [Retours](returns.md) |
| `ReturnCreditNotes` | Émet la note de crédit d'un retour ; public — voyez [Notes de crédit et bordereaux](credit-notes-and-slips.md) |
| `ReturnSlips` | Le bordereau d'un retour autorisé, et son PDF ; public, enregistré quand un moteur de rendu l'est |
