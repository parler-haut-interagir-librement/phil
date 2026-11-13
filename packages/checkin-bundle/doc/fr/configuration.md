<!-- phil-dev source-sha256=027170d7b5fe5790be14b1a1737927f31b80352a5245897245cc22bf7bbcf097 -->
# Configuration

```yaml
phil_checkin:
    enabled: false                 # the endpoints answer 404 until true
    clock: clock                   # the PSR-20 clock scans are timed on
    store: doctrine                # "doctrine", "memory" for tests, or the service id of a CheckInLedger
    connection: default            # the Doctrine connection of the table
    catalog: null                  # the service id of a CredentialCatalog; null asks the tagged catalogs
    policy:
        early_seconds: 0           # 0 to 86400: how early before its opening a credential is let in
        anti_passback: true        # whether a holder inside must check out before coming in again
    rules:
        checkpoints: {}            # the ExpressionLanguage rule of each checkpoint, by its name
        provider: null             # the service id of an AdmissionRules that gives the rules instead
        attributes: null           # the service id of a CredentialAttributes the rules read
        timezone: null             # the time zone of `now` in the rules; null keeps the clock's
    offline:
        manifest_seconds: 43200    # 60 to 604800: how long a device may use a manifest
        max_credentials: 10000     # 1 to 100000: how many credentials a manifest carries
        labels: false              # whether manifests carry the labels, such as the holders' names
        min_code_bits: 64          # 0 to 128: the strength a code needs to be carried by a manifest; 0 takes any
        batch_limit: 500           # 1 to 500: how many scans a device sends at once
        clock_skew_seconds: 300    # 0 to 3600: how far ahead of the server a device clock may be
        max_age_seconds: 172800    # 60 to 2592000: how old a scan made offline may be
```

| Clé | Effet |
|---|---|
| `enabled` | Désactivé, chaque point de terminaison répond `{"error": "disabled"}` avec 404 ; les services existent encore pour l'usage propre de l'application. |
| `store` | `doctrine` enregistre `DoctrineCheckInLedger` sur `connection`, et son écouteur de schéma quand l'ORM est installé. `memory` garde les enregistrements dans le processus, pour les tests. Toute autre valeur est l'identifiant de service du registre de l'application. Le registre a l'alias `CheckInLedger`. |
| `catalog` | Le catalogue des appareils hors ligne : il donne ses manifestes à `CheckpointDesks`, enregistre `OfflineSync`, ouvre les points de terminaison de manifeste et de synchronisation, et indique au contrôleur qu'il peut passer hors ligne. Nul, il interroge ensemble les services étiquetés `phil_checkin.credential_catalog`, que la configuration automatique étiquette ; sans aucun, les postes n'enregistrent qu'en ligne et ces points de terminaison répondent 404. |
| `policy` | La `AdmissionPolicy` du poste, des manifestes et de la synchronisation. |
| `rules` | La règle d'admission de chaque point de contrôle, ci-dessous. |
| `offline` | Les bornes de `ManifestIssuer`, de `OfflineSync` et de la longueur d'un journal. Un manifeste porte sa clé : `min_code_bits` écarte donc les codes assez courts pour être devinés à partir d'un appareil perdu ; ne l'abaissez que pour des codes connus pour être aléatoires. |

Une valeur hors de ses bornes fait échouer la construction du conteneur, avec son chemin.

## Règles

La règle d'un point de contrôle est une expression de l'ExpressionLanguage de Symfony que doit satisfaire un titre
d'accès pour y entrer, en plus de la politique :

```yaml
phil_checkin:
    rules:
        timezone: America/Toronto
        attributes: App\Checkin\TicketAttributes
        checkpoints:
            bar: "attributes['age'] >= 18"
            workshop: "'workshop' in attributes['products'] and now.format('H') >= '09'"
            backstage: "credential.label != null and checkpoint == 'backstage'"
```

| Variable | Ce que c'est |
|---|---|
| `credential` | Le `Phil\Checkin\Credential` : `credential.id`, `credential.label`, `credential.maxEntries`, `credential.validFrom`, `credential.validUntil`, `credential.status`. |
| `attributes` | Ce que répond à propos du titre d'accès le `CredentialAttributes` que désigne `attributes` ; un tableau vide sans lui. |
| `checkpoint` | Le nom du point de contrôle. |
| `now` | L'instant de l'horloge dans `timezone`, un `DateTimeImmutable` : `now.format('H')` donne l'heure, `now.format('N')` le jour de la semaine, 1 pour le lundi. |

Un titre d'accès n'entre que si sa règle vaut elle-même `true`. Refusé, il reçoit la réponse `wrong_checkpoint`, est
enregistré comme tout refus et est écarté du manifeste hors ligne du point de contrôle. Une règle ne s'applique qu'aux
entrées : un titulaire à l'intérieur peut toujours sortir.

En ligne, une règle est évaluée à chaque lecture ; hors ligne, à l'émission du manifeste — une règle sur l'heure ne
ferme la porte hors ligne qu'au manifeste suivant, une minute après sa fermeture en ligne. Un journal synchronisé est
enregistré avec les décisions de l'appareil, que la règle avait filtrées.

Chaque règle de la configuration est vérifiée à la compilation du conteneur : une règle de plus de 1000 octets, une
règle qui ne s'analyse pas ou une règle qui lit une autre variable arrête la construction en nommant le point de
contrôle. Une règle qui échoue sur un titre d'accès — un attribut manquant, une méthode qui lance une exception — le
refuse. `rules.provider` remplace la configuration : voyez [Points d'extension](extension-points.md#règles-dadmission).
