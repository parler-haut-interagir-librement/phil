<!-- phil-dev source-sha256=e48204ca26982c7be51d8a6abcae679e715378e49fc558af3fb545cd08814a78 -->
# Notifications

## Le type

Quand `phil/notifications-bundle` est activé, `NotifyMentionedMember` écoute `MemberMentioned` et envoie le type
`member.mentioned` — ou le `notification_type` de la configuration — :

| Partie | Valeur |
|---|---|
| Destinataire | `Member::$recipient`, la référence du membre, sauf si l'annuaire nomme un autre identifiant |
| Sujet | Le sujet, `comment:7`, dont le `SubjectResolver` de l'application donne le libellé et l'URL |
| Données | `context`, le contexte, quand ce n'est pas le sujet |
| Acteur | La référence de l'auteur, dont le `RecipientDirectory` de l'application donne le nom |

Seulement des identifiants : le texte qui mentionne, les noms et les adresses sont cherchés au moment d'afficher la
notification. Le sujet et l'acteur suivent les identifiants du bundle de notifications : lettres, chiffres, `_`, `.`,
`:` et `-`.

Le type est déclaré avec les canaux `site` et `email`, et `web_push` quand le Web Push du bundle de notifications est
activé (`phil_notifications.web_push`) : un membre qui l'a activé dans un navigateur y reçoit ses mentions.
L'application le modifie comme n'importe quel autre type :

```yaml
phil_notifications:
    types:
        member.mentioned:
            channels: [site, web_push]
            collapse: true
```

## Traductions

Le bundle est fourni avec le titre et le corps du type dans le domaine `phil_notifications`, en anglais et en
français :

| Clé | Anglais |
|---|---|
| `member.mentioned.title` | `%actor% mentioned you` |
| `member.mentioned.body` | `%actor% mentioned you in %subject%.` |

Une application les remplace dans ses propres `translations/phil_notifications.<locale>.*` ; avec un autre
`notification_type`, elle écrit les clés de ce type.

## La préférence des membres

Avant de notifier, l'écouteur interroge `MentionPreferences::notifies($recipient)` :

| Source | Quand |
|---|---|
| Le service nommé par `preferences` | Quand la configuration en nomme un |
| `SettingsMentionPreferences` | Quand `phil/settings-bundle` est activé : le réglage `notifications.mentions`, activé par défaut, dans les préférences de notification des membres |
| `AlwaysNotify` | Sinon |

Désactiver les mentions n'arrête que la notification : la mention est toujours enregistrée et `MemberMentioned`
toujours diffusé. Les canaux — courriel, Web Push, l'empreinte périodique — restent le choix de chaque membre dans les
préférences propres aux notifications.
