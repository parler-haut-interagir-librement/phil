# Connected terminals

A connected card terminal is driven by the server, never by the till. The till asks the server for a charge; the
server creates the core's payment and asks the terminal's processor, through an adapter, to show it on the device;
the processor's signed notification settles the payment, and the session counts it. The till runs no script of the
processor, and the processor's secret key stays on the server.

## Registering a terminal

A terminal sits at a register and names the adapter that speaks to its processor and the processor's identifier of
the device:

```php
$registers->addTerminal('t1', 'front', adapter: 'fake', device: 'tmr_FRONT', label: 'Counter reader');
$registers->addTerminal('t2', 'phone-ada', adapter: 'fake', device: 'tmr_TAP', label: 'Tap reader', pinEntry: false);
```

A terminal takes payments that ask for a PIN by default, and only the main register has one of those: a mobile
register's terminal is registered with `pinEntry: false`, and a main register with a PIN terminal cannot become mobile
([devices.md](devices.md)). `Terminal::retire()` takes one out of service; it keeps its payments.

## Taking a charge

```php
$payment = $terminalPayments->charge($order, $session, 't1', $clock->now());          // all that is left to pay
$payment = $terminalPayments->charge($order, $session, 't1', $clock->now(), $amount); // or part of it, split
```

`charge()` checks that the session is open and that the terminal is at its register, starts a payment of the core
under the terminals' method — `card_present`, the `terminals.method` option —, and asks the adapter to present it.
The charge carries the payment's own amount, never one taken from the till's request. If the adapter fails, the
payment is given up at once, so that it no longer holds its share of the order, and the failure is thrown.

The till then shows what the server says: it polls the payment's state, or listens for it through Mercure. A charge
still on the device is withdrawn with `cancel($payment)`, which asks the adapter to cancel it and gives the payment up;
a notification that comes later still applies, as the core's always do.

## Notifications

The processor posts its events to `TerminalNotificationController` ([installation.md](installation.md#routing)).
`TerminalPayments::notify()` hands them to the core's `Payments::notify()`, through `TerminalGateway`, which asks
each adapter in turn whether it recognises the request — by its signature header — and lets the first one verify
and read it. Once the payment is captured, the session counts it, once, with the processor's reference as its
approval; a failed or cancelled payment is not counted.

| Answer | Meaning |
|---|---|
| `200` | Applied, already applied, refused by the payment's state, or for a payment nobody presented |
| `400` | No adapter recognises the notification, or its signature does not verify |
| `413` | A body over 64 KiB |

## Refunds

The core's `Payments::refund()` asks `TerminalGateway`, which asks the adapter of the payment's terminal to refund it
under the processor's reference, with the refund's idempotency key — the same on every try. As with every processor,
the refund is recorded when the notification says it happened.

## Writing an adapter

One adapter per processor, in the application or a package of its own, named after the processor's service rather
than given a package name of its own. It implements `Application\Contract\PaymentTerminal`, tagged
`phil_commerce_pos.payment_terminal` by autoconfiguration:

| Method | Contract |
|---|---|
| `name(): string` | The name terminals give, up to 32 lowercase letters, digits or `_` |
| `present(TerminalCharge $charge): string` | Asks the processor to show the charge on `$charge->device`, carrying `$charge->payment` so that its notifications name it; returns the processor's reference of the request, 1 to 255 visible ASCII characters |
| `cancel(string $device, string $processorReference): void` | Withdraws a request the device still shows |
| `refund(string $processorReference, Money $amount, string $idempotencyKey): void` | Asks for a refund, handing the key to the processor as its idempotency key so that a refund asked again is made once; the notification records it |
| `recognises(array $headers): bool` | Whether a request is this processor's, by a header it always sends |
| `read(string $payload, array $headers): PaymentNotification` | Verifies the signature — with `hash_equals()` or the processor's library — and reads the event; its external reference is the payment id the charge carried. Throws `InvalidNotification` otherwise |

An adapter keeps no state between two requests: the processor's client and its secret are its only properties.

## The fake terminal

`Infrastructure\Terminal\FakePaymentTerminal`, named `fake`, pretends: it remembers what it was asked and signs the
notifications a test asks it for with an HMAC-SHA256 of the body under a key derived from the
kernel secret by HKDF-SHA256 (`FakePaymentTerminal::USAGE`), in the
`x-fake-terminal-signature` header. It is registered only when `terminals.fake` is `true` — for tests, demos and a
till's development —, and the kernel resets it between two requests.

```php
[$payload, $headers] = $fake->notification('evt-1', $payment->id, PaymentEvent::Captured);
```
