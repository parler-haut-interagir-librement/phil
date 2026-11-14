# Network

Several sites of a network can share one notification centre: a site takes the notifications another one sends into
its own centre, under the sender's origin. This bundle receives them; how they are sent — by each site, or by a
central platform — is the application's.

## Receiving

With phil/standard-webhooks-bundle configured with the senders' verification keys, and `remote.enabled`:

```yaml
phil_notifications:
    remote:
        enabled: true
        origins: [events, shop]
```

The endpoint takes a Standard Webhooks delivery of type `notification.created`, its signature verified before the
controller runs:

```json
{
    "type": "notification.created",
    "timestamp": "2026-11-08T12:00:00Z",
    "data": {
        "origin": "events",
        "notification": "comment.replied",
        "recipients": ["42"],
        "subject": {"type": "comment", "id": "981"},
        "data": {"event": 7},
        "actor": "17"
    }
}
```

| Delivery | Answer |
|---|---|
| Unsigned, stale, signed by no trusted key | `400` or `401`, by phil/standard-webhooks-bundle |
| A body that is no envelope | `400` |
| Another type, a payload of the wrong shape, an origin not in `origins`, a type this site does not declare | `422`, which the sender does not retry |
| Already processed — with the bundle's processed ids | `204`, not delivered again |
| Taken | `204` |

The recipients are identifiers this site knows: the network shares its members' identifiers. The type must be declared
on this site too, with the channels this site gives it.

## The port

`RemoteNotificationReceiver::receive(RemoteNotification $notification)` takes in a verified notification.
`ForwardingRemoteReceiver` delivers it through `Notifications::send()` with its origin; an application names its own
under `remote.receiver` to map identifiers or types between sites.
