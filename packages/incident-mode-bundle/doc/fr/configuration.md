<!-- phil-dev source-sha256=fde4e0e47ee99c0e32e3af3dc4faa7fb5611f59bea50cc3ff986cdac50e6cf9d -->
# Configuration

Chaque option de `phil_incident_mode`, avec sa valeur par défaut :

```yaml
# config/packages/phil_incident_mode.yaml
phil_incident_mode:
    connection: default
    built_in_presets: true
    without_presets: []
    presets: {}
    spare_established: []
    established_members: null
    current_member: null
    layout: '@PhilIncidentMode/layout.html.twig'
    kit_subdirectory: null
    timezone: null
    checkpoints:
        status: 503
    admin:
        enabled: true
        role: ROLE_ADMIN
        page_size: 20
    lifting:
        messenger: true
        bus: messenger.default_bus
        schedule:
            enabled: true
            name: incident_mode
            frequency: '5 minutes'
    audit:
        enabled: true
        segment: incidents
    privacy: true
```

| Option | Ce qu'elle fait |
|---|---|
| `connection` | La connexion DBAL de la table `phil_incident_activation`, par son nom dans la configuration de DoctrineBundle. |
| `built_in_presets` | Si les cinq préréglages intégrés sont offerts avant ceux de l'application ; à `false`, seuls ceux de `presets` le sont. |
| `without_presets` | Les préréglages intégrés laissés de côté, par nom. |
| `presets` | Les préréglages de l'application, par nom, après les intégrés ; un préréglage portant un nom intégré le remplace à sa place. |
| `spare_established` | Les restrictions dont un incident laisse libres les membres établis ; aucune quand la liste est vide. |
| `established_members` | Le service qui implémente `Phil\IncidentMode\Contract\EstablishedMembers` ; `NoEstablishedMembers`, personne, quand c'est `null`. |
| `current_member` | Le service qui implémente `Application\Contract\CurrentMember` ; l'identifiant de l'utilisateur de Symfony Security quand c'est `null`. |
| `layout` | Le gabarit Twig que les pages étendent, avec les blocs `title` et `main`. |
| `kit_subdirectory` | L'emplacement des recettes du kit, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand c'est `null`. |
| `timezone` | Le fuseau horaire dans lequel les fins sont écrites pour les visiteurs, et nommé ; le fuseau horaire par défaut de PHP quand c'est `null`. |
| `checkpoints.status` | Le statut d'un refus : 503, 429, 423 ou 403, toujours avec `Retry-After`. |
| `admin` | La page d'administration ; `admin: false` la laisse de côté. `role` peut démarrer, prolonger et lever un incident ; `page_size` incidents du journal par page, de 1 à 200. |
| `lifting.messenger` | Si chaque incident envoie son message de levée différé sur `lifting.bus`, quand ce bus existe. |
| `lifting.schedule` | Le balayage récurrent, une planification de Symfony Scheduler nommée `name`, exécutée toutes les `frequency` comme `RecurringMessage::every()` les lit ; `enabled: false` la laisse de côté. |
| `audit` | Les incidents écrits dans le segment `segment` de la piste de phil/audit-bundle, quand il est enregistré ; `audit: false` les laisse de côté. |
| `privacy` | Si les incidents qu'un membre a démarrés ou levés répondent aux demandes de phil/privacy-bundle, quand il est enregistré. |

## Les préréglages

Un préréglage est un libellé, les restrictions qu'il suspend — au moins une, des noms intégrés ou ceux de
l'application —, la durée qu'un incident propose et la plus longue qu'il peut durer, prolongations comprises :

```yaml
phil_incident_mode:
    without_presets: [suspend-new-messages]
    presets:
        suspend-signups: { label: 'Close the sign-ups', restrictions: [signups], proposed: 1h, longest: 1d }
        suspend-listings: { label: 'Suspend new listings', restrictions: [new-listings, uploads], proposed: 4h, longest: 2d }
```

Une durée s'écrit comme la console la lit : un nombre de minutes (`90`), ou des nombres avec leur unité — `m`, `min`,
`minutes` ; `h`, `hour`, `hours` ; `d`, `day`, `days` — additionnés (`1d 12h`). Elle va d'une minute à quatre-vingt-dix
jours. Le nom d'un préréglage et celui d'une restriction sont une lettre minuscule, puis jusqu'à 63 lettres minuscules,
chiffres et traits d'union. Un préréglage que la bibliothèque refuse — un nom, une restriction, une durée proposée plus
longue que la plus longue permise — fait échouer la construction du conteneur avec la raison.

Les préréglages intégrés ont leurs libellés en anglais et en français ; le libellé de ceux de l'application est affiché
tel qu'il est écrit.

## La table

La bibliothèque nomme sa table `phil_incident_activation` ; le bundle choisit sa connexion, pas son nom. Deux
applications qui partagent une base de données gardent leurs incidents séparés par des connexions vers des schémas ou
des bases de données différents.

## Les membres établis

```yaml
phil_incident_mode:
    spare_established: [new-posts, new-comments]
    established_members: App\Trust\EstablishedMembers
```

`read-only` couvre chaque restriction : épargner `new-posts` seulement laisse un membre établi restreint pendant qu'un
incident en lecture seule est en vigueur ; énumérer aussi `read-only` le laisse passer. Un visiteur n'est jamais épargné.
