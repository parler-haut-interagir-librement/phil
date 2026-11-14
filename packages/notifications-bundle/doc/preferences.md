# Preferences

`RecipientSettings` is what a recipient chose:

| Setting | Effect |
|---|---|
| `digest` | `never` — email as each notification comes —, `daily` or `weekly` |
| `quietHours` | `QuietHours::of('22:00', '07:00', 'America/Toronto')`, or none |
| `optOuts` | `type:channel` or `*:channel` |
| `locale` | The locale their notifications are written in; the current one when null |

## Where they are kept

| `preferences` | Source |
|---|---|
| `config` | The `defaults` of the configuration, for everyone |
| `dbal` | One row per recipient in `phil_notification_preferences`, the defaults for the others |
| a service id | The application's own `NotificationPreferences` |

With `dbal`, the application saves a recipient's choice from its own settings page:

```php
$preferences->save($member->id, new RecipientSettings(DigestFrequency::Weekly, optOuts: ['*:web_push'], locale: 'fr'));
$preferences->forget($member->id);
```

## The port

`NotificationPreferences::settingsFor(string $recipient): RecipientSettings` is read as each notification is
delivered and as each digest is sent. An application that keeps its members' settings elsewhere — a settings package,
a profile table — implements it and names it under `preferences`; an implementation answers fast, from a cache if it
must.
