<!-- phil-dev source-sha256=992c94f97ec1880b95de9b3de344a0a08b45b4610c8913aea6fc085cb2d2a372 -->
# Fonctionnalités

`FeatureAccess` dit ce qu'un abonné peut utiliser : les fonctionnalités de chaque offre dont il détient un abonnement
qui donne accès — à l'essai, actif, ou en souffrance tant que durent ses nouvelles tentatives —, et celles des
abonnements des organisations auxquelles il appartient ([organizations.md](organizations.md)). Un abonnement en
souffrance donne ce que gardent les étapes de relance de son offre à ce nombre de jours de retard : toutes les
fonctionnalités par défaut, quelques-unes, ou aucune ([dunning.md](dunning.md)).

```php
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;

$access->features($accountId);         // ["projects:unlimited", "export"]
$access->grants($accountId, 'export'); // true
```

- Chaque fonctionnalité apparaît une seule fois, dans l'ordre où les offres les énumèrent, celles de l'abonné
  d'abord.
- Un abonnement en attente ou terminé ne donne rien.
- Les noms des fonctionnalités sont ceux de l'application : le module ne fait que les porter de l'offre à l'abonné. Un
  indicateur de fonctionnalité qui dépend d'un palier lit `grants()`.
- **Les fonctionnalités d'un abonné sont lues une seule fois** et gardées : une page qui vérifie cinq fonctionnalités
  lit les abonnements une fois avec ceux de ses organisations (`SubscriptionRepository::forSubscribers()`), et leurs
  offres en une requête de plus (`OfferRepository::findByCodes()`), quel que soit le nombre d'abonnements de l'abonné.
  `Subscriptions`, `Gifts` et `Seats` oublient un abonné dont ils changent l'abonnement ou la place — l'abonnement, un
  paiement, un échec, une annulation, le balayage, un cadeau échangé, une place —, oublier une organisation oublie les
  membres lus par elle, `forget(subscriber)` le fait pour un changement fait ailleurs, et le service est étiqueté
  `kernel.reset` : un *worker* de longue durée les relit donc pour chaque requête ou chaque message.
