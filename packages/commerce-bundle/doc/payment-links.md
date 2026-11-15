# Payment links

A payment link lets whoever holds it pay a placed order, until it expires or is revoked: what a store sends by email
for an order taken by phone, or for the balance of a changed order.

```php
use Phil\CommerceBundle\Application\PaymentLinks;

// $links: PaymentLinks, from the container
[$link, $token] = $links->create($order, $now);      // 72 hours by default
[$link, $token] = $links->create($order, $now, 24);  // or as many hours as given

// The buyer follows the link.
$order = $links->open($token, $now);                 // null for a link that opens nothing
[$payment, $start] = $payments->start($order, 'card', $now);

$links->revokeAll($order, $now);                     // once paid otherwise, changed or cancelled
```

- **The token is shown once**: 192 random bits; only its SHA-256 is stored. A malformed token is refused without a
  lookup.
- **A link refuses alike** whether it is unknown, revoked, expired, or its order is no longer placed or owes nothing:
  whoever follows it learns nothing more.
- **The link carries no amount**: following it starts a payment of what the order still lacks, computed by the server.
- **A link lasts** `phil_commerce.payment_links.default_hours` by default, 72, and never longer than `max_hours`,
  720.

Links are stored in `phil_commerce_payment_link` through `PaymentLinkRepository` (`DoctrinePaymentLinkRepository`
with DoctrineBundle).
