# Alerts

The alerts are on by default: `alerts: false` turns them off. The first event of a group and a regression then tell
someone, by a notification to the recipients or the audience named in the configuration, else by a chat message. With
neither recipients nor a chatter in the notifier's configuration, there is no one to tell: the alerts are in the
degraded state.

## The degraded state

Alerts that are on but reach no one never fail the build nor a capture. The bundle says so in three places:

- **the log**: each alert that would have been sent, once per new group or regression and never per event, becomes a
  warning that names the group by its fingerprint, from `UndeliveredAlerter`;
- **the administration pages**: both pages show a notice at the top;
- **the command** `phil:error-tracking:status`, which prints `Alerts: degraded.` and the way out, and still succeeds.

The service `AlertDestination` tells the state to the application's own code, for a dashboard of its own:
`notification`, `chat`, `disabled` when `alerts: false`, or `none` when degraded. Turning the alerts off on purpose is
not degraded.

## By notification

With phil/notifications-bundle registered, `alerts.recipients` or `alerts.audience`, never both:

```yaml
phil_error_tracking:
    alerts:
        recipients: ['12', '57']       # identifiers of the application, or
        # audience: operators          # an audience of an AudienceProvider
```

The bundle declares the notification type `phil_error_tracking.group_alert`, on the `site` and `email` channels,
important, sent at once rather than left to a digest, collapsing into an unread one about the same group. Declaring the
same type under `phil_notifications.types` overrides any of that: a `broadcast` to `chat/discord`, other channels. Its
title and body are in the `phil_notifications` translation domain, in English and French.

The notification carries the fingerprint of the group, the occurrence and the count: the message on the bus holds
identifiers only. `ErrorGroupSubjects` reads the title of the group from the store when the notification is shown, and
its address when the administration pages are enabled. A group purged since shows no title. A recipient that is not an
identifier fails the container build.

Delivery is `Notifications`', which goes through its own bus: `alerts.async` and `alerts.transport` do not apply.

## By chat

With no recipient nor audience, the alert is a chat message through the chatter of `symfony/notifier`, on the transport
of `alerts.transport` or the chatter's default one:

```text
[prod] New error: RuntimeException: The export failed
In App\Export::run (src/Export.php:42)
Seen 1 time(s) on 1.4.0 — group 3f2c1a9e0b1d
```

A repeated event never alerts, nor does an event of an ignored group. The message names the group by its title, its
culprit, its count, its last release and the start of its fingerprint; the events themselves stay in the store. It is
cut to 1,800 characters.

## Queued or at once

With `alerts.async` (the default) and a Messenger bus, the tracker dispatches an `AlertErrorGroup` message — the
fingerprint and the occurrence, nothing else — on the default bus, and `AlertErrorGroupHandler`, a handler of that bus
only, reads the group and sends the message. An application with other buses beside it, a query bus for instance,
does not find the handler on them.
Route the message to an asynchronous transport, so that a slow chat never slows the request that failed:

```yaml
framework:
    messenger:
        routing:
            Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup: async
```

A group purged before the message is handled sends nothing. A failed alert is never captured: its failure would raise
another alert. With `alerts.async: false`, the message is sent in the request; a chat that fails is then a failed
capture, logged once by the guard. Without a Messenger bus, the message is sent in the request as well.

## The transport

```yaml
framework:
    notifier:
        chatter_transports:
            discord: '%env(DISCORD_DSN)%'

phil_error_tracking:
    alerts:
        transport: discord
```
