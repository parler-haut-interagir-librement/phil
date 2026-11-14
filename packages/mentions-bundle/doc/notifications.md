# Notifications

## The type

With `phil/notifications-bundle` enabled, `NotifyMentionedMember` listens to `MemberMentioned` and sends the type
`member.mentioned` — or the `notification_type` of the configuration —:

| Part | Value |
|---|---|
| Recipient | `Member::$recipient`, the member's reference unless the directory names another identifier |
| Subject | The subject, `comment:7`, whose label and URL the application's `SubjectResolver` gives |
| Data | `context`, the context, when it is not the subject |
| Actor | The author's reference, whose name the application's `RecipientDirectory` gives |

Identifiers only: the text that mentions, the names and the addresses are looked up when the notification is shown.
The subject and the actor follow the identifiers of the notifications bundle: letters, digits, `_`, `.`, `:` and `-`.

The type is declared with the channels `site` and `email`, and `web_push` when the notifications bundle's own Web
Push is enabled (`phil_notifications.web_push`): a member who turned it on in a browser gets their mentions there. The
application changes it like any other type:

```yaml
phil_notifications:
    types:
        member.mentioned:
            channels: [site, web_push]
            collapse: true
```

## Translations

The bundle ships the title and the body of the type in the `phil_notifications` domain, in English and French:

| Key | English |
|---|---|
| `member.mentioned.title` | `%actor% mentioned you` |
| `member.mentioned.body` | `%actor% mentioned you in %subject%.` |

An application overrides them in its own `translations/phil_notifications.<locale>.*`; with another
`notification_type`, it writes the keys of that type.

## The members' preference

Before notifying, the listener asks `MentionPreferences::notifies($recipient)`:

| Source | When |
|---|---|
| The service named by `preferences` | When the configuration names one |
| `SettingsMentionPreferences` | When `phil/settings-bundle` is enabled: the setting `notifications.mentions`, on by default, in the members' notification preferences |
| `AlwaysNotify` | Otherwise |

Turning mentions off stops the notification only: the mention is still recorded and `MemberMentioned` still
dispatched. The channels — email, Web Push, the digest — stay each member's choice in the notifications' own
preferences.
