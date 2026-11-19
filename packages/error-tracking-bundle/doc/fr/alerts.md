<!-- phil-dev source-sha256=35829724762ee49eec9cacc06afd24f57aa1b0a30dac828bac4c0435f648d892 -->
# Alertes

Les alertes sont activées par défaut : `alerts: false` les désactive. Le premier événement d'un groupe et une
régression avertissent alors quelqu'un, par une notification aux destinataires ou à l'audience nommés dans la
configuration, sinon par un message de clavardage. Sans destinataires ni *chatter* dans la configuration du notifier,
il n'y a personne à avertir : les alertes sont dans l'état dégradé.

## L'état dégradé

Des alertes activées qui n'atteignent personne ne font jamais échouer la construction ni une capture. Le bundle le dit
à trois endroits :

- **le journal** : chaque alerte qui aurait été envoyée, une fois par nouveau groupe ou par régression et jamais par
  événement, devient un avertissement qui nomme le groupe par son empreinte, depuis `UndeliveredAlerter` ;
- **les pages d'administration** : les deux pages montrent un avis en haut ;
- **la commande** `phil:error-tracking:status`, qui affiche `Alerts: degraded.` et la marche à suivre, et réussit quand
  même.

Le service `AlertDestination` dit l'état au code de l'application, pour un tableau de bord à elle : `notification`,
`chat`, `disabled` quand `alerts: false`, ou `none` quand l'état est dégradé. Désactiver les alertes exprès n'est pas
un état dégradé.

## Par notification

Avec phil/notifications-bundle enregistré, `alerts.recipients` ou `alerts.audience`, jamais les deux :

```yaml
phil_error_tracking:
    alerts:
        recipients: ['12', '57']       # identifiers of the application, or
        # audience: operators          # an audience of an AudienceProvider
```

Le bundle déclare le type de notification `phil_error_tracking.group_alert`, sur les canaux `site` et `email`,
important, envoyé tout de suite plutôt que laissé à un résumé, et qui se fond dans une notification non lue au sujet
du même groupe. Déclarer le même type sous `phil_notifications.types` remplace n'importe lequel de ces choix : un
`broadcast` vers `chat/discord`, d'autres canaux. Son titre et son corps sont dans le domaine de traduction
`phil_notifications`, en anglais et en français.

La notification porte l'empreinte du groupe, l'occurrence et le compte : le message sur le bus ne contient que des
identifiants. `ErrorGroupSubjects` lit le titre du groupe dans le magasin quand la notification est affichée, ainsi
que son adresse quand les pages d'administration sont activées. Un groupe purgé depuis n'affiche aucun titre. Un
destinataire qui n'est pas un identifiant fait échouer la construction du conteneur.

La livraison revient à `Notifications`, qui passe par son propre bus : `alerts.async` et `alerts.transport` ne
s'appliquent pas.

## Par clavardage

Sans destinataire ni audience, l'alerte est un message de clavardage par le *chatter* de `symfony/notifier`, sur le
transport d'`alerts.transport` ou sur celui du *chatter* par défaut :

```text
[prod] New error: RuntimeException: The export failed
In App\Export::run (src/Export.php:42)
Seen 1 time(s) on 1.4.0 — group 3f2c1a9e0b1d
```

Un événement répété n'alerte jamais, pas plus qu'un événement d'un groupe ignoré. Le message nomme le groupe par son
titre, son coupable, son compte, sa dernière version et le début de son empreinte ; les événements eux-mêmes restent
dans le magasin. Il est coupé à 1 800 caractères.

## En file d'attente ou tout de suite

Avec `alerts.async` (la valeur par défaut) et un bus Messenger, le traqueur répartit un message `AlertErrorGroup`
(l'empreinte et l'occurrence, rien d'autre), et `AlertErrorGroupHandler` lit le groupe et envoie le message. Routez le
message vers un transport asynchrone, pour qu'un clavardage lent ne ralentisse jamais la requête qui a échoué :

```yaml
framework:
    messenger:
        routing:
            Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup: async
```

Un groupe purgé avant le traitement du message n'envoie rien. Une alerte ratée n'est jamais capturée : son échec
lèverait une autre alerte. Avec `alerts.async: false`, le message est envoyé dans la requête ; un clavardage qui
échoue est alors une capture ratée, journalisée une fois par la garde. Sans bus Messenger, le message est aussi envoyé
dans la requête.

## Le transport

```yaml
framework:
    notifier:
        chatter_transports:
            discord: '%env(DISCORD_DSN)%'

phil_error_tracking:
    alerts:
        transport: discord
```
