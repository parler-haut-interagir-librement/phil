# The order's life cycle

An order's states — cart, quoted, placed, cancelled — are described by `symfony/workflow`: the state machine
`phil_commerce_order`, built by `OrderWorkflow` and registered as the service `phil_commerce.order_workflow`. The
order's own methods make each move, under their invariants; the workflow checks the move against its graph and its
guards, and dispatches the events of `symfony/workflow`, so that an automation, an audit or a notification follows
orders without knowing the bundle.

| Transition | From | To | Does |
|---|---|---|---|
| `quote` | `cart` | `quoted` | `Order::quote()` |
| `place` | `cart`, `quoted` | `placed` | `Order::place()` |
| `reopen` | `quoted`, `placed` | `cart` | `Order::reopen()` |
| `cancel` | `cart`, `quoted`, `placed` | `cancelled` | `Order::cancel()` |
| `expire` | `placed` | `cancelled` | `Order::expire()` ([expiry.md](expiry.md)) |

When the container has a `workflow.registry` — FrameworkBundle with any workflow configured —, the bundle adds the
order workflow to it for `Order` subjects, so `workflow:dump phil_commerce_order` draws it and the registry hands it
out like the application's own workflows.

## Moving an order

`OrderLifecycle` takes the transitions: `quote()`, `place()`, `reopen()`, `cancel()`, `expire()`, or `apply()` with
the transition's name. Each takes the moment of the move and, optionally, the stable identifier of who makes it.

```php
use Phil\CommerceBundle\Application\OrderLifecycle;

// $lifecycle: OrderLifecycle, from the container
$lifecycle->cancel($order, $now, 'employee-7');
```

- **The graph and the guards decide first**: a transition they do not open throws `OrderLocked`, and nothing moves.
  A listener on `workflow.phil_commerce_order.guard.<transition>` blocks a transition the way it blocks any workflow
  of Symfony.
- **The order makes the move**: `OrderMarkingStore` stores a place by calling the order's method, with the moment the
  workflow's context holds under `at`; an invariant the order keeps — an empty cart is never placed — throws from
  there.
- **The events follow**: `leave`, `transition`, `enter`, `entered`, `completed` and `announce`, under
  `workflow.phil_commerce_order.*`, as Symfony names them.
- **Each move is journalled**: one `OrderStateChange` per move, with the transition, who made it and when, through
  `OrderJournal` (`DoctrineOrderJournal` with DoctrineBundle, table `phil_commerce_order_state_change`). The line is
  stored with the application's next commit.

`OrderModifier::place()` places through the workflow when it is wired, which the bundle does; `OrderChanges` and
`OrderExpiry` move the order through it too.

## Forcing a move

Someone allowed to may move an order to a state outside its transitions — a cancelled order placed again after a
mistake. `OrderLifecycle::force()` takes the target state, who forces it and why:

```php
$lifecycle->force($order, OrderState::Placed, 'manager-2', 'Cancelled by mistake at the counter', $now);
```

- **Nobody forces by default**: the `TransitionForcing` port answers, and `DenyForcing`, the bundle's alias, refuses
  everyone. An application allows its managers by aliasing its own adapter, usually over its security voters
  ([extension-points.md](extension-points.md#transitionforcing)).
- **The reason is required** and checked before the order moves: 1 to 255 characters on one line.
- **The move is journalled** as a forced `OrderStateChange` with its reason, then announced by `OrderStateForced`,
  dispatched by its class. No workflow event is dispatched: no transition was taken.
- **The order keeps its invariants**: a forced move to the same state, or of an empty order to placed, is refused;
  moving to placed sets the moment placed, to cancelled the moment cancelled, and leaving cancelled forgets an expiry.

## Announcing a cancellation

Whenever an order ends in `cancelled` — by the `cancel` or the `expire` transition, or forced there —,
`OrderLifecycle` dispatches `OrderCancelled`, by its class, after the journal's line: the order's number, the state it
left, its channel, its customer's stable identifier, whether it expired unpaid, who cancelled it and the moment. A
forced cancellation is announced after `OrderStateForced`. An activity feed or a loyalty programme withdraws what the
order earned, and a returns module stops taking returns of it ([extension-points.md](extension-points.md#events)).
An order that `OrderExpiry` expires without the life cycle — when it is built without one — is not announced; the
bundle always wires the life cycle.

## Statuses

An application names its own statuses, each attached to the state it belongs to — "awaiting pickup" within placed —
under `phil_commerce.order_statuses`:

```yaml
phil_commerce:
    order_statuses:
        awaiting_pickup: placed
        backordered: placed
```

`OrderLifecycle::changeStatus()` sets one, or clears it with `null`; a status the configuration does not declare, or
that belongs to another state than the order's, is refused. `Order::status()` reads it, stored in the column
`status`. **A move of the state clears the status**, so a status never outlives the state it describes; reports
group orders by their canonical state first, and by status within it.
