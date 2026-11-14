<!-- phil-dev source-sha256=634d3303b3a4d4ae86d591a7a6c05a234979abe8ad7d6533e1f79068ecc15e0e -->
# Notifications et vie privée

## Les préférences de notification

Avec `phil/notifications-bundle`, le bundle déclare cinq réglages dans la section `notifications` et les fournit comme
`NotificationPreferences` de ce bundle :

| Réglage | Type | Valeur par défaut | Devient |
|---|---|---|---|
| `notifications.digest` | choix parmi `never`, `daily`, `weekly` | `never` | La fréquence du résumé |
| `notifications.email` | booléen | `true` | `*:email` désactivé quand faux |
| `notifications.web_push` | booléen | `true` | `*:web_push` désactivé quand faux |
| `notifications.quiet_start` | texte, `HH:MM` ou rien | `''` | Le début des heures de silence |
| `notifications.quiet_end` | texte, `HH:MM` ou rien | `''` | Leur fin |

Un destinataire est un utilisateur, par le même identifiant. Les heures de silence s'appliquent quand leur début et
leur fin sont tous deux donnés, dans le fuseau horaire de `notifications.timezone`. Le centre des préférences est
l'écran de ces choix. Le bundle des notifications les lit à la livraison de chaque notification, à partir des réglages
lus une fois par requête.

## Exportation et effacement

Avec `phil/privacy-bundle`, le module `settings` répond aux demandes d'accès et d'effacement d'une personne, la
personne concernée étant l'utilisateur :

- l'exportation liste chaque valeur enregistrée pour elle, lue comme son réglage la lit, ou telle qu'enregistrée quand
  son réglage n'est plus déclaré. Une personne qui n'a jamais choisi n'a rien à exporter : les valeurs par défaut ne
  disent rien d'elle ;
- l'effacement retire chaque ligne de l'utilisateur, déclare `phil_settings` comme supprimé, et peut être relancé.

Le témoin des préférences vit dans le navigateur du visiteur, et le site n'en conserve rien.
