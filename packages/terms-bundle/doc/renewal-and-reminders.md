# Renewal and reminders

## Renewal

A policy with `renew_after_days` asks its acceptance again once that many days have passed since the member's latest
acceptance of the version required or a later one. The gate then sends the member to the acceptance page as for a new
version; the new acceptance is added beside the earlier ones.

## The notices of a new version

With `notifications` enabled, `phil/notifications-bundle` carries the notices. The bundle declares the two types, with
the channels `site` and `email`, and ships their title and body in the `phil_notifications` domain, in English and
French. `published_type` and `reminder_type` rename them; with another name, the application writes the keys of that
type. The application changes a type like any other:

```yaml
phil_notifications:
    types:
        phil_terms.reminder: { channels: [email] }
```

Publishing a material version sends `phil_terms.published` to the audience `phil_terms:<policy>`. `OutstandingAudience`
turns it into the members who have not accepted the latest material version yet, when the notice is delivered rather
than when it is sent. Each notice is about the subject `phil_terms_policy:<policy>` and carries the version number as
its `version` data: identifiers only, which the application's templates turn into words and a link.

## Reminders

With `reminders` enabled, the schedule `phil_terms` dispatches `SendTermsReminders` at `reminders.frequency`, run by
`messenger:consume scheduler_phil_terms`. For each required policy, the handler reminds the members who will not be in
order `reminders.days_ahead` days from now — they have not accepted the version required then, a version taking effect
by then, or their acceptance lapses by then — of the version in force then, 500 members at a time.

The reminders and the notices go through a `TermsNotifier`: the one of `notifications`, or the service `notifier`
names, which may send them any other way.

## Who is a member

The notices, the reminders and `phil:terms:outstanding` read the members from the `MemberSource` the configuration
names. Without one, the members are those who accepted a version of any policy: a member who never accepted anything
is not among them, so an application that must reach every member names its own source.
