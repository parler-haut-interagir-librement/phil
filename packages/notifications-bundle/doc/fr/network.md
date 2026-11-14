<!-- phil-dev source-sha256=60862d15e23981d7ab8193a3f48ed667946039867bd6abed47da463b52586cc4 -->
# Réseau

Plusieurs sites d'un réseau peuvent partager un seul centre de notifications : un site reçoit dans son propre centre
les notifications qu'un autre envoie, sous l'origine de l'expéditeur. Ce bundle les reçoit ; la façon de les envoyer
(par chaque site, ou par une plateforme centrale) appartient à l'application.

## Réception

Avec phil/standard-webhooks-bundle configuré avec les clés de vérification des expéditeurs, et `remote.enabled` :

```yaml
phil_notifications:
    remote:
        enabled: true
        origins: [events, shop]
```

Le point de terminaison accepte une livraison Standard Webhooks de type `notification.created`, sa signature vérifiée
avant l'exécution du contrôleur :

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

| Livraison | Réponse |
|---|---|
| Non signée, périmée, signée par aucune clé de confiance | `400` ou `401`, par phil/standard-webhooks-bundle |
| Un corps qui n'est pas une enveloppe | `400` |
| Un autre type, une charge utile de mauvaise forme, une origine absente d'`origins`, un type que ce site ne déclare pas | `422`, que l'expéditeur ne retente pas |
| Déjà traitée, avec les identifiants traités du bundle | `204`, non livrée de nouveau |
| Reçue | `204` |

Les destinataires sont des identifiants que ce site connaît : le réseau partage les identifiants de ses membres. Le
type doit être déclaré sur ce site aussi, avec les canaux que ce site lui donne.

## Le port

`RemoteNotificationReceiver::receive(RemoteNotification $notification)` reçoit une notification vérifiée.
`ForwardingRemoteReceiver` la livre par `Notifications::send()` avec son origine ; une application nomme le sien sous
`remote.receiver` pour faire correspondre les identifiants ou les types d'un site à l'autre.
