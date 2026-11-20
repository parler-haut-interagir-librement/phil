# Main and mobile registers

A shop has one main register per location — the tablet at the counter, the centre of the shop — and as many mobile
registers as there are sellers with a phone.

| | Main register | Mobile register |
|---|---|---|
| Cash and cards that need no PIN | Yes | Yes |
| Payments that ask for a PIN | Yes, on its PIN terminals | No |
| Picking up a sale another register held | Yes, a sale of its location | Only its own |
| Selling without the network | Yes, through the offline queue | No: it waits for the network |

```php
$registers->create('front', 'Counter', $cad, 5, location: 'store-1', role: RegisterRole::Main);
$registers->create('phone-ada', 'Ada\'s phone', $cad, 5, location: 'store-1');   // RegisterRole::Mobile by default
$registers->assign('front', RegisterRole::Main);
```

`Registers` keeps one active main register per location — registers that name no location count as one location —
and refuses a second one with `PosRefused`. A main register with a terminal that takes PIN payments stays main.

## A sale started on a phone, finished at the counter

A seller builds the cart on their phone and holds it; the main register of the same location picks it up, takes the
PIN payment and rings it up:

```php
$checkout->hold($order, 'phone-ada', 'Red coat', 'ada', $clock->now());
$order = $checkout->resume($order->number, 'front');
```

A held sale is picked up at the register that held it, or at the main register of its location; anywhere else,
`resume()` throws `PosRefused`. **Picking a sale up takes the network**: the server holds the cart, and no local
network joins the devices. The offline queue never picks up a sale either — a sale whose id is an existing order's
number is refused ([offline.md](offline.md)).

## The cashier switch by PIN

On a shared device someone already unlocked, a cashier picks their name and types their PIN to ring up as
themselves. The switch is **off by default**; enabled with a rate limiter ([installation.md](installation.md#configuring)),
it needs `symfony/password-hasher` and `symfony/rate-limiter`.

```php
$cashierSwitch->setPin('ada', '2580', $clock->now());                   // 4 to 8 digits
$user = $cashierSwitch->switchTo('front', 'ada', '2580', $clock->now()); // 'ada', or CashierPinRefused
```

- A PIN belongs to a user of the application, named by its identifier; the switch keeps no account of its own and
  signs nobody in. It tells the application which user typed the right PIN; the application decides what that user
  may do, through Symfony's security, and asks for its stronger proof — a passkey — before a refund if it wishes.
- A PIN is kept only as a hash of PHP's native algorithm, through Symfony PasswordHasher, and hashed again on the next
  right PIN when the algorithm's cost has changed.
- One digit repeated (`1111`) and a run (`1234`, `7890`, `9876`) are refused.
- Attempts are counted by the rate limiter, keyed by register and user; the right PIN resets the count. Past the
  limit, `CashierPinRefused::$retryAfter` says when to try again.
- A wrong PIN and a user without one read the same, and take as long.
