# Notifications and privacy

## The notification preferences

With `phil/notifications-bundle`, the bundle declares five settings in the `notifications` section and provides them
as that bundle's `NotificationPreferences`:

| Setting | Type | Default | Becomes |
|---|---|---|---|
| `notifications.digest` | choice of `never`, `daily`, `weekly` | `never` | The digest frequency |
| `notifications.email` | boolean | `true` | `*:email` turned off when false |
| `notifications.web_push` | boolean | `true` | `*:web_push` turned off when false |
| `notifications.quiet_start` | text, `HH:MM` or nothing | `''` | The start of the quiet hours |
| `notifications.quiet_end` | text, `HH:MM` or nothing | `''` | Their end |

A recipient is a user, by the same identifier. The quiet hours apply when both their start and their end are given, in
the time zone of `notifications.timezone`. The preferences centre is the screen of these choices; the notifications
bundle reads them as each notification is delivered, from the settings read once per request.

## Export and erasure

With `phil/privacy-bundle`, the module `settings` answers a person's access and erasure requests, the data subject
being the user:

- the export lists every value stored for them, read as its setting reads it, or as stored when its setting is no longer
  declared — a person who never chose has nothing to export, the defaults saying nothing of them;
- the erasure removes every row of the user, reports `phil_settings` as deleted, and can run again.

The preference cookie lives in the visitor's browser, and the site keeps nothing of it.
