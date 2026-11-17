# Messenger

## When Messenger's own outbox fits

Symfony Messenger 8.2 ships an outbox and an inbox of its own:

- the `outbox` option of a transport stores the messages in an outbox transport, inside the current database
  transaction, and forwards them to their target transport when the outbox transport is consumed;
- the `inbox` option records the handled messages in a table, in the transaction of their handlers, so that a
  redelivered message is not handled twice.

**Use them when the code already dispatches Messenger messages** in a Doctrine transaction: an application's own
handlers, a bundle built on Messenger. They need no codec and no relay of their own.

**Use phil/outbox when a library knows nothing of Messenger**: a plain PHP library that writes through its own store
and tells PSR-14 listeners, whose events must survive a failure between its write and its dispatch. phil/outbox also
keeps the records of an ordering key in order, and its dead records and retention in one table.

The two meet: the bridge below relays the events of the outbox to a bus, whose routing, transports, retries and inbox
then apply to them.

## The bridge to a bus

```yaml
phil_outbox:
    messenger:
        enabled: true
        bus: messenger.default_bus
```

The relay dispatches each rebuilt event to the bus instead of the PSR-14 dispatcher. A record is delivered once the
bus accepted its event: handled by a synchronous handler, or sent to its transport. A handler that fails, or a
transport that refuses the message, leaves the record to be tried again after the backoff.

A message sent through a transport carries what its codec rebuilt: identifiers only, as Messenger messages should.
`RecordedEvent` carries the record's identifier, which a handler keeps to handle a record once; with an asynchronous
transport, enable the transport's `inbox` as well, so that a message the transport redelivers is handled once.
