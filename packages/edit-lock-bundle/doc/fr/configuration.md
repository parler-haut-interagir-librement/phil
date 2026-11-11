<!-- phil-dev source-sha256=6ab2b6e3f1ef6174a5fa72797bdbd32540006e0a46bcc0930e1d272aa1908fb8 -->
# Configuration

```yaml
phil_edit_lock:
    seconds: 30                       # how long a lease lasts without a heartbeat, 5 to 3600
    store: doctrine                   # or "memory", for tests
    connection: default               # the Doctrine connection of the table
    holder_provider: ~                # a HolderProvider service; the endpoints need it
    policy: ~                         # an EditPolicy service; the endpoints need it
    block_store: ~                    # a BlockStore service; none only tells saves to the other writers
    mercure:
        enabled: ~                    # on when MercureBundle is registered
        hub: Symfony\Component\Mercure\HubInterface
        topic_prefix: 'urn:phil:edit-lock:'
        private: true
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `seconds` | `30` | La durée d'un bail depuis sa dernière pulsation ; le contrôleur pulse trois fois plus souvent |
| `store` | `doctrine` | `doctrine` conserve les baux dans `phil_edit_lease` ; `memory` dans le processus, pour les tests seulement |
| `connection` | `default` | La connexion dont le service `doctrine.dbal.<name>_connection` contient la table |
| `holder_provider` | aucune | Le service qui nomme le rédacteur courant |
| `policy` | aucune | Le service qui dit qui peut modifier et qui peut reprendre |
| `block_store` | aucune | Le service qui conserve les blocs enregistrés |
| `mercure.enabled` | MercureBundle enregistré | Si la présence et les enregistrements sont poussés |
| `mercure.hub` | `HubInterface` | Le service du hub, un autre hub de MercureBundle par exemple |
| `mercure.topic_prefix` | `urn:phil:edit-lock:` | Placé devant le nom de la ressource pour former son sujet |
| `mercure.private` | `true` | Les mises à jour privées n'atteignent que les abonnés autorisés pour le sujet |

## Services

| Service | Classe | Public |
|---|---|---|
| `Phil\EditLockBundle\Application\EditLocks` | `EditLocks` | oui |
| `Phil\EditLockBundle\Application\BlockSaves` | `BlockSaves` | oui |
| `Phil\EditLockBundle\Domain\Repository\LeaseRepository` | alias de `DoctrineLeaseRepository` ou de `InMemoryLeaseRepository` | oui |
| `Phil\EditLockBundle\Application\Broadcaster` | alias de `MercureBroadcaster` ou de `NullBroadcaster` | non |
| `Phil\EditLockBundle\Infrastructure\Http\ClientConfig` | `ClientConfig` | oui |
| `Phil\EditLockBundle\Infrastructure\Http\EditLockController` | `EditLockController` | oui |

Sans `policy`, `EditLocks` utilise `DefaultEditPolicy` — tout le monde peut modifier, personne ne peut reprendre — pour
le propre code de l'application ; les points de terminaison répondent `404` tant que `holder_provider` et `policy` ne
sont pas tous deux définis. `BlockSaves` vérifie les blocs avec le service `Phil\Ujo\Ujo` de `phil/ujo-bundle` lorsqu'il
est enregistré, sinon avec le registre par défaut de `phil/ujo`.
