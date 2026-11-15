<!-- phil-dev source-sha256=e40233443596b71f3a6e3c299ac264d5af4cfdc2bcf01832ed30757f8c40bd20 -->
# Les liens de paiement

Un lien de paiement permet à qui le détient de payer une commande passée, jusqu'à ce qu'il expire ou soit révoqué :
ce qu'une boutique envoie par courriel pour une commande prise par téléphone, ou pour le solde d'une commande changée.

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

- **Le jeton est montré une fois** : 192 bits aléatoires ; seule son empreinte SHA-256 est stockée. Un jeton mal formé
  est refusé sans recherche.
- **Un lien refuse de la même façon** qu'il soit inconnu, révoqué, expiré, ou que sa commande ne soit plus passée ou ne
  doive plus rien : qui le suit n'apprend rien de plus.
- **Le lien ne porte aucun montant** : le suivre commence un paiement de ce qui manque encore à la commande, calculé par
  le serveur.
- **Un lien dure** `phil_commerce.payment_links.default_hours` par défaut, 72, et jamais plus que `max_hours`, 720.

Les liens sont stockés dans `phil_commerce_payment_link` par `PaymentLinkRepository` (`DoctrinePaymentLinkRepository`
avec DoctrineBundle).
