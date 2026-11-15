# Channels

A channel sells: a site of the application with its own domains — a shop and the second shop it runs under another
name —, or a point of sale. Each channel has its currency, its locale, its theme and the tax zone its orders start
in; the modules give it its own selection of products, prices and promotions.

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

| Member of `Channel` | Rule |
|---|---|
| `code` | Up to 64 lowercase letters, digits, `_`, `.` or `-`: what an order, a price book or a promotion names |
| `name()`, `rename()` | Not blank |
| `currency` | The currency of its orders |
| `locale()`, `theme()`, `present()` | `fr`, `fr_CA` or `sr_Latn_RS`; a theme as the application names it, or none |
| `taxZone()`, `startOrdersIn()` | The tax zone its orders start in — a store's own —, or none until the buyer's is known |
| `hostnames()`, `answerOn()`, `stopAnsweringOn()`, `answersOn()` | Its domains, lowercase; a point of sale has none |
| `close()`, `reopen()` | A closed channel answers no request and opens no order; its orders stay |

| Method of `Channels` | Effect |
|---|---|
| `forHost(host)` | The open channel answering on the host, its port ignored; `UnknownChannel` otherwise |
| `open(code)` | The open channel of that code; `UnknownChannel` otherwise |
| `newOrder(channel, number)` | A cart in the channel's currency, sold through it, starting in its tax zone |

## A channel's selection

A channel offers a sellable through an `Offer`: how far it shows it, a `Visibility` at one of three levels, then
whether an order may hold it and whether its price is shown.

| `Visibility` | In the channel's lists and search | Its page |
|---|---|---|
| `Listed` | Yes | Shown |
| `Unlisted` | No | Shown to whoever has its direct link: a convention edition, hidden tickets, a presale |
| `Hidden` | No | Not shown |

| Offer | What a page shows |
|---|---|
| `Offer::full()` | Listed, its price and the button that buys it |
| `Offer::of(Visibility::Unlisted)` | Its page by direct link, its price and the button that buys it |
| `Offer::of(Visibility::Listed, orderable: false)` | Its page and its price, without the button: sold out for good, sold elsewhere |
| `Offer::of(Visibility::Listed, orderable: false, priceShown: false)` | Its page alone: an announcement |
| `Offer::hidden()` | Nothing |

An orderable offer always shows its price, and `Offer::of()` refuses one that does not; a hidden offer is neither
orderable nor priced.

Every `SellableAvailability` — tagged `phil_commerce.sellable_availability` — answers for what it knows, and the
strictest answer wins: the stricter visibility, orderable and priced only when every answer says so; hidden by one is
hidden. `SellableOffers::of($reference, $channel)` gives that answer to the pages. `OrderModifier` asks the same
selections when a line is added and when `changeAudience()` moves the order to another channel, and refuses with
`NotSoldThere` anything no order may hold, before anything changes; what only its direct link reaches is sold as
anything listed. The catalogue implements the port for its products; a sellable no implementation restricts is offered
in full everywhere.

## Storage

With DoctrineBundle, a channel is one row of `phil_commerce_channel`: `code` (primary key), `name`, `currency`,
`locale`, `theme`, `tax_zone`, `hostnames` (JSON), `active`.
