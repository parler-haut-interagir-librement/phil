<!-- phil-dev source-sha256=f33441264f13e77837e2d6a883f423638c0531ec90f00d9272f15aaa784324be -->
# Préférences

`RecipientSettings` est ce qu'a choisi un destinataire :

| Réglage | Effet |
|---|---|
| `digest` | `never` (un courriel à l'arrivée de chaque notification), `daily` ou `weekly` |
| `quietHours` | `QuietHours::of('22:00', '07:00', 'America/Toronto')`, ou aucune |
| `optOuts` | `type:channel` ou `*:channel` |
| `locale` | La locale dans laquelle ses notifications sont rédigées ; la courante quand elle vaut null |

## Où elles sont gardées

| `preferences` | Source |
|---|---|
| `config` | Les `defaults` de la configuration, pour tout le monde |
| `dbal` | Une ligne par destinataire dans `phil_notification_preferences`, les valeurs par défaut pour les autres |
| un identifiant de service | Le `NotificationPreferences` propre à l'application |

Avec `dbal`, l'application enregistre le choix d'un destinataire depuis sa propre page de réglages :

```php
$preferences->save($member->id, new RecipientSettings(DigestFrequency::Weekly, optOuts: ['*:web_push'], locale: 'fr'));
$preferences->forget($member->id);
```

## Le port

`NotificationPreferences::settingsFor(string $recipient): RecipientSettings` est lu à la livraison de chaque
notification et à l'envoi de chaque résumé. Une application qui garde les réglages de ses membres ailleurs (un paquet de
réglages, une table de profils) l'implémente et le nomme sous `preferences` ; une implémentation répond vite, depuis un
cache s'il le faut.
