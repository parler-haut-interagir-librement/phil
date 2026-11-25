<!-- phil-dev source-sha256=658fb2b337057851475b72fe36cd7547969b3ed4d4bfb7d5a0892e7ff3fdab87 -->
# Le cycle de vie d'une commande

Les états d'une commande — panier, soumission, passée, annulée — sont décrits par `symfony/workflow` : la machine à
états `phil_commerce_order`, construite par `OrderWorkflow` et enregistrée comme le service
`phil_commerce.order_workflow`. Les méthodes de la commande font chaque passage, sous leurs invariants ; le workflow
vérifie le passage contre son graphe et ses gardes, et envoie les événements de `symfony/workflow`, si bien qu'une
automatisation, un audit ou une notification suit les commandes sans connaître le bundle.

| Transition | De | À | Fait |
|---|---|---|---|
| `quote` | `cart` | `quoted` | `Order::quote()` |
| `place` | `cart`, `quoted` | `placed` | `Order::place()` |
| `reopen` | `quoted`, `placed` | `cart` | `Order::reopen()` |
| `cancel` | `cart`, `quoted`, `placed` | `cancelled` | `Order::cancel()` |
| `expire` | `placed` | `cancelled` | `Order::expire()` ([expiry.md](expiry.md)) |

Quand le conteneur a un `workflow.registry` — FrameworkBundle avec un workflow configuré, quel qu'il soit —, le bundle
y ajoute le workflow des commandes pour les sujets `Order`, si bien que `workflow:dump phil_commerce_order` le dessine
et que le registre le remet comme les workflows de l'application.

## Déplacer une commande

`OrderLifecycle` prend les transitions : `quote()`, `place()`, `reopen()`, `cancel()`, `expire()`, ou `apply()` avec le
nom de la transition. Chacune prend le moment du passage et, facultativement, l'identifiant stable de qui le fait.

```php
use Phil\CommerceBundle\Application\OrderLifecycle;

// $lifecycle: OrderLifecycle, from the container
$lifecycle->cancel($order, $now, 'employee-7');
```

- **Le graphe et les gardes décident d'abord** : une transition qu'ils n'ouvrent pas lève `OrderLocked`, et rien ne
  bouge. Un écouteur de `workflow.phil_commerce_order.guard.<transition>` bloque une transition comme il bloque celle de
  n'importe quel workflow de Symfony.
- **La commande fait le passage** : `OrderMarkingStore` stocke une place en appelant la méthode de la commande, avec le
  moment que le contexte du workflow garde sous `at` ; un invariant que la commande respecte — un panier vide n'est
  jamais passé — lève depuis là.
- **Les événements suivent** : `leave`, `transition`, `enter`, `entered`, `completed` et `announce`, sous
  `workflow.phil_commerce_order.*`, comme Symfony les nomme.
- **Chaque passage est journalisé** : un `OrderStateChange` par passage, avec la transition, qui l'a fait et quand, par
  `OrderJournal` (`DoctrineOrderJournal` avec DoctrineBundle, table `phil_commerce_order_state_change`). La ligne est
  stockée au prochain commit de l'application.

`OrderModifier::place()` passe par le workflow quand il est câblé, ce que fait le bundle ; `OrderChanges` et
`OrderExpiry` font aussi passer la commande par lui.

## Forcer une transition

Une personne autorisée peut faire passer une commande à un état hors de ses transitions — une commande annulée qu'on
passe de nouveau après une erreur. `OrderLifecycle::force()` prend l'état visé, qui le force et pourquoi :

```php
$lifecycle->force($order, OrderState::Placed, 'manager-2', 'Cancelled by mistake at the counter', $now);
```

- **Personne ne force par défaut** : le port `TransitionForcing` répond, et `DenyForcing`, l'alias du bundle, refuse
  tout le monde. Une application autorise ses gestionnaires en créant l'alias vers son propre adaptateur, d'ordinaire
  au-dessus de ses voters de sécurité ([extension-points.md](extension-points.md#transitionforcing)).
- **La raison est exigée** et vérifiée avant que la commande bouge : de 1 à 255 caractères sur une seule ligne.
- **Le passage est journalisé** comme un `OrderStateChange` forcé avec sa raison, puis annoncé par `OrderStateForced`,
  envoyé par sa classe. Aucun événement du workflow n'est envoyé : aucune transition n'a été prise.
- **La commande garde ses invariants** : un passage forcé vers le même état, ou d'une commande vide vers passée, est
  refusé ; passer à passée fixe le moment du passage, à annulée le moment de l'annulation, et quitter annulée oublie une
  expiration.
- **`Order::force()` est interne** et ne fait pas partie de l'API publique : elle fait passer la commande sans
  demander à personne, et `OrderLifecycle::force()` est son seul appelant. Une application, ou un autre module, force
  par le cycle de vie.

## Annoncer une annulation

Chaque fois qu'une commande se termine dans `cancelled` — par la transition `cancel` ou `expire`, ou forcée vers cet
état —, `OrderLifecycle` envoie `OrderCancelled`, par sa classe, après la ligne du journal : le numéro de la commande,
l'état qu'elle a quitté, son canal, l'identifiant stable de son client, si elle a expiré impayée, qui l'a annulée et
le moment. Une annulation forcée est annoncée après `OrderStateForced`. Un fil d'activité ou un programme de fidélité
retire ce que la commande a rapporté, et un module de retours cesse d'en accepter les retours
([extension-points.md](extension-points.md#événements)). Une commande que `OrderExpiry` fait expirer sans le cycle de
vie — quand il est construit sans lui — n'est pas annoncée ; le bundle câble toujours le cycle de vie.

## Statuts

Une application nomme ses propres statuts, chacun rattaché à l'état auquel il appartient — « en attente de ramassage »
dans passée — sous `phil_commerce.order_statuses` :

```yaml
phil_commerce:
    order_statuses:
        awaiting_pickup: placed
        backordered: placed
```

`OrderLifecycle::changeStatus()` en fixe un, ou l'efface avec `null` ; un statut que la configuration ne déclare pas,
ou qui appartient à un autre état que celui de la commande, est refusé. `Order::status()` le lit, stocké dans la
colonne `status`. **Un passage d'état efface le statut**, si bien qu'un statut ne survit jamais à l'état qu'il décrit ;
les rapports regroupent d'abord les commandes par leur état canonique, puis par statut à l'intérieur.
