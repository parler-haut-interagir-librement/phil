<!-- phil-dev source-sha256=b17b66f96ec461c763ea49d206e02fc9e8c15747a3f9756d68e6029b4f71eee6 -->
# Canaux

Un canal vend : un site de l'application avec ses propres domaines — une boutique, et la deuxième boutique qu'elle
exploite sous un autre nom —, ou un point de vente. Chaque canal a sa devise, sa locale, son thème et la zone de taxe
dans laquelle ses commandes commencent ; les modules lui donnent sa propre sélection de produits, de prix et de
promotions.

```php
use Phil\CommerceBundle\Application\Channels;
use Phil\CommerceBundle\Domain\Model\Channel;

$second = Channel::create('second-shop', 'La deuxième boutique', Currency::of('CAD'), 'fr_CA', theme: 'second', taxZone: 'QC');
$second->answerOn('second-shop.example.ca');
$channelRepository->add($second);

// For each request
$channel = $channels->forHost($request->getHost());     // Channels, from the container
$order = $channels->newOrder($channel, $orderNumber);   // in its currency, through it, in its tax zone
```

| Membre de `Channel` | Règle |
|---|---|
| `code` | Jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-` : ce que nomme une commande, une grille de prix ou une promotion |
| `name()`, `rename()` | Pas vide |
| `currency` | La devise de ses commandes |
| `locale()`, `theme()`, `present()` | `fr`, `fr_CA` ou `sr_Latn_RS` ; un thème tel que l'application le nomme, ou aucun |
| `taxZone()`, `startOrdersIn()` | La zone de taxe dans laquelle ses commandes commencent — celle d'un magasin —, ou aucune tant que celle de l'acheteur n'est pas connue |
| `hostnames()`, `answerOn()`, `stopAnsweringOn()`, `answersOn()` | Ses domaines, en minuscules ; un point de vente n'en a aucun |
| `close()`, `reopen()` | Un canal fermé ne répond à aucune requête et n'ouvre aucune commande ; ses commandes restent |

| Méthode de `Channels` | Effet |
|---|---|
| `forHost(host)` | Le canal ouvert qui répond sur l'hôte, son port ignoré ; `UnknownChannel` sinon |
| `open(code)` | Le canal ouvert de ce code ; `UnknownChannel` sinon |
| `newOrder(channel, number)` | Un panier dans la devise du canal, vendu par lui, qui commence dans sa zone de taxe |

## La sélection d'un canal

Un canal offre un article vendable par une `Offer` : jusqu'où il le montre, une `Visibility` à l'un de trois niveaux,
puis si une commande peut le contenir et si son prix est montré.

| `Visibility` | Dans les listes et la recherche du canal | Sa page |
|---|---|---|
| `Listed` | Oui | Montrée |
| `Unlisted` | Non | Montrée à qui a son lien direct : une édition de congrès, des billets cachés, une prévente |
| `Hidden` | Non | Non montrée |

| Offre | Ce que montre une page |
|---|---|
| `Offer::full()` | Listé, son prix et le bouton qui l'achète |
| `Offer::of(Visibility::Unlisted)` | Sa page par lien direct, son prix et le bouton qui l'achète |
| `Offer::of(Visibility::Listed, orderable: false)` | Sa page et son prix, sans le bouton : épuisé pour de bon, vendu ailleurs |
| `Offer::of(Visibility::Listed, orderable: false, priceShown: false)` | Sa page seulement : une annonce |
| `Offer::hidden()` | Rien |

Une offre commandable montre toujours son prix, et `Offer::of()` en refuse une qui ne le fait pas ; une offre cachée
n'est ni commandable ni avec prix.

Chaque `SellableAvailability` — étiquetée `phil_commerce.sellable_availability` — répond de ce qu'elle connaît, et la
réponse la plus stricte l'emporte : la visibilité la plus stricte, commandable et avec prix seulement quand chaque
réponse le dit ; cachée par une seule, c'est cachée. `SellableOffers::of($reference, $channel)` donne cette réponse aux
pages. `OrderModifier` interroge les mêmes sélections quand une ligne est ajoutée et quand `changeAudience()` déplace la
commande vers un autre canal, et refuse par `NotSoldThere` tout ce qu'aucune commande ne peut contenir, avant que quoi
que ce soit change ; ce que seul son lien direct atteint se vend comme tout ce qui est listé. Le catalogue implémente
le port pour ses produits ; un article vendable qu'aucune implémentation ne restreint est offert en plein partout.

## Stockage

Avec DoctrineBundle, un canal est une rangée de `phil_commerce_channel` : `code` (clé primaire), `name`, `currency`,
`locale`, `theme`, `tax_zone`, `hostnames` (JSON), `active`.
