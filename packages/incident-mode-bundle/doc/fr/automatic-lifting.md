<!-- phil-dev source-sha256=1f3d640f76a8fa81ceb5fb0fa774cfd4ca1b93d21c90537ac410345b5b9f8338 -->
# Levée automatique

Un incident prend fin à sa fin, que quoi que ce soit s'exécute ou non : passé sa fin, il ne suspend rien, et chaque
point de contrôle laisse passer la requête. La levée **consigne** cette fin dans le journal — levé à sa fin, sans
acteur — et envoie l'`IncidentLifted` de la bibliothèque, une seule fois.

## Le message différé

Avec Messenger installé et le bus de `lifting.bus` enregistré, chaque incident démarré ou prolongé envoie le
`Phil\IncidentMode\Messenger\LiftIncident` de la bibliothèque, qui porte l'identifiant de l'incident et rien d'autre,
différé jusqu'à sa fin. Son *handler*, enregistré par le bundle, consigne la fin dès qu'elle est échue ; un incident
prolongé depuis attend le message de sa prolongation. Acheminez-le vers un transport qui prend en charge les délais :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

`lifting.messenger: false` n'envoie aucun message, et le balayage seul consigne les fins.

## Le balayage récurrent

Un transport peut perdre un message. Avec Symfony Scheduler installé, le bundle enregistre `IncidentSchedule`, la
planification `incident_mode`, qui envoie le `LiftExpiredIncidents` de la bibliothèque toutes les cinq minutes : son
*handler* consigne la fin de chaque incident qui l'a dépassée, cent à la fois. Un *worker* le consomme :

```sh
php bin/console messenger:consume async scheduler_incident_mode
```

`lifting.schedule.name` et `lifting.schedule.frequency` changent son nom et sa fréquence ; `lifting.schedule: false` le
laisse de côté. Deux *workers* qui consignent la même fin envoient son événement une seule fois : la bibliothèque lève
par une mise à jour conditionnelle.

## Sans Messenger

Quand le bus n'est pas là, le bundle n'enregistre ni le message ni la planification, et une tâche cron consigne les
fins :

```cron
*/5 * * * * php /srv/app/bin/console phil:incident:lift --expired
```

## Dans un worker

Les services du bundle ne gardent rien entre deux requêtes : chaque réponse lit les incidents en vigueur à ce
moment-là, de sorte qu'un *worker* de longue durée voit un incident prendre fin à sa fin.
