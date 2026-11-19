<!-- phil-dev source-sha256=5487fd8af6e18f354893b3ab45826bf0726d6b5a93134cbe6a50ac9ca6841b29 -->
<!-- phil-dev source-sha256=0 -->
# Transporteurs

Les tarifs et les étiquettes d'un transporteur, et ce que chaque expédition a vraiment coûté à la boutique, gardé à
côté de ce que l'acheteur a payé pour la livraison. Le prix de livraison que paie l'acheteur reste celui du mode de
livraison (voyez [Livraison](delivery.md)) : rien ici ne change une commande.

## Le port

`Application\Port\CarrierGateway` est un transporteur : son code, ses tarifs pour un colis, et l'étiquette qu'il
produit ou vend pour un colis. Chaque transporteur est un adaptateur à part, nommé d'après lui ; le port et les
services gardent des noms neutres. Un service qui implémente le port reçoit l'étiquette
`phil_commerce_logistics.carrier` par autoconfiguration.

| Méthode | Retourne |
|---|---|
| `carrier()` | Le code du transporteur, `canada_post` par exemple |
| `rates(RateRequest)` | Une liste de `RateQuote` : le code et le nom du service, le coût pour la boutique, les jours ouvrables prévus |
| `label(LabelRequest)` | Un `Label` : le numéro de suivi, le coût pour la boutique, les octets du document et leur type de média |

Un transporteur qui refuse une requête ou ne répond pas lève `CarrierRefused::unavailable()`, avec ses propres
messages et jamais ses identifiants.

## Tarifs, étiquettes et coûts

```php
$request = new RateRequest(
    ShippingAddress::of('CA', 'K2B 8J6'),
    ShippingAddress::of('CA', 'J0E 1X0'),
    Parcel::of(1_250, 300, 200, 100),   // grams, then millimetres
);

$labels->rates($request);                  // every carrier that answers, the cheapest first
$labels->rates($request, 'canada_post');   // one carrier; its failure shows

$label = $labels->label('canada_post', LabelRequest::of($from, $to, $parcel, 'DOM.RP', $order->number));
$shipment = $shipments->ship($order, $clock->now(), carrier: 'canada_post', tracking: $label->tracking);
$labels->recordLabel($shipment, $label, $clock->now());

// A label bought at the counter
$labels->record($shipment, 'canada_post', 'counter', Money::ofDecimal('14.25', Currency::of('CAD')), $clock->now());

$labels->margin($order);   // ShippingMargin: charged, cost, margin()
```

- `ShippingAddress` demande un pays et un code postal pour un tarif ; une étiquette demande un nom, une ligne de rue,
  une ville et, au Canada et aux États-Unis, un code postal. Les champs tiennent sur une ligne d'au plus 128
  caractères.
- `Parcel` pèse de 1 g à 1 t, avec ses trois dimensions ou aucune.
- Un coût par expédition : un deuxième est refusé par `CarrierRefused::alreadyRecorded()`.
- `margin()` additionne les coûts notés de la commande contre ses ajustements de livraison ; un coût dans une autre
  devise que celle de la commande est refusé.

## Postes Canada

`Infrastructure\CanadaPost\CanadaPostCarrier` interroge l'API REST de Postes Canada en JSON par le client HTTP de
Symfony. Il se connecte avec un jeton d'accès OAuth 2.0 pour l'application de développeur de la boutique, gardé
jusqu'à 60 secondes avant son expiration et oublié entre les requêtes d'un *worker* (`kernel.reset`). Il demande
`POST /prices` pour les tarifs et `POST /shipments` pour une étiquette, puis va chercher le PDF de l'étiquette au lien
que donne la réponse ; le prix vient de la réponse, ou de son lien `price`.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    carriers:
        canada_post:
            enabled: true
            api_key: '%env(CANADA_POST_API_KEY)%'
            api_secret: '%env(CANADA_POST_API_SECRET)%'
            customer_number: '%env(CANADA_POST_CUSTOMER_NUMBER)%'
            contract_id: '%env(default::CANADA_POST_CONTRACT_ID)%'   # commercial rates; null for counter rates
            base_uri: '%env(CANADA_POST_API_URI)%'                   # the test or production gateway
            token_uri: '%env(CANADA_POST_TOKEN_URI)%'
            language: en-CA                                          # or fr-CA
```

Les identifiants viennent de l'environnement ou du coffre des secrets, jamais du code. L'adaptateur a besoin du
service `http_client` de FrameworkBundle.

| Garde | Comportement |
|---|---|
| HTTPS seulement | Les adresses de l'API et des jetons sont en `https://` |
| Liens | Un lien d'une réponse n'est suivi que vers le schéma, l'hôte et le port de l'API elle-même : le jeton ne part jamais vers un autre hôte ; aucune redirection n'est suivie. Le lien est lu par `phil/url`, comme le fait un navigateur, et envoyé tel qu'il a été lu : un nom d'utilisateur ou un mot de passe le refuse, une barre oblique inverse termine son hôte |
| Taille | Une réponse JSON est lue jusqu'à 1 Mio, une étiquette jusqu'à 5 Mio, morceau par morceau |
| Temps | 10 secondes d'inactivité, 30 secondes en tout, par requête |
| Réponses | Le type de chaque champ est vérifié ; un champ absent ou étrange refuse toute la réponse ; les montants sont en dollars canadiens, jamais négatifs |

Les noms des champs JSON sont réunis dans une seule classe, `CanadaPostPayloads`. Les tests roulent sur des réponses de
la forme de celles de l'API, gardées dans `tests/Fixtures/CanadaPost`, et n'appellent jamais le réseau.

**Limite** : les noms des champs n'ont pas été comparés au document OpenAPI du portail des développeurs de Postes
Canada, publié seulement aux développeurs inscrits. Comparez `CanadaPostPayloads` à ce document avant le premier appel
en production ; un champ que l'API nomme autrement fait refuser la réponse entière plutôt que lire une mauvaise valeur.

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_logistics_shipping_cost` | `shipment` (clé), `order_number`, `carrier`, `service`, `tracking`, `cost` (unités mineures), `currency`, `recorded_at` ; indexée par commande |
