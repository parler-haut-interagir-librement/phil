<!-- phil-dev source-sha256=ce5673d7e707eeca00a9707c6b7ad628ea2dc46d2b638e6d6c0bcd147edc4137 -->
# Points d'extension

Ce qu'une application implémente, écoute, configure ou utilise. Les ressources sont des références de
`phil/resource`.

## `TrashHandler`

`Phil\TrashBundle\Application\TrashHandler` : un type de ressource que l'application met à la corbeille. Un service
qui l'implémente reçoit l'étiquette `phil_trash.handler` par autoconfiguration. Voir [Gestionnaires](handlers.md).

| Méthode | Contrat |
|---|---|
| `resourceKey(): string` | Le type : lettres minuscules, chiffres, `_`, `.` ou `-` |
| `supports(object $resource): bool` | Si le gestionnaire garde cet objet |
| `capture(object $resource): Captured` | L'identifiant, un titre pour la corbeille, ce qu'il faut pour la restaurer en JSON, et son parent |
| `remove(object $resource): void` | Retire les lignes de la ressource, les siennes et celles de ses enfants, sans flush ; ses fichiers restent |
| `parentExists(ResourceReference $parent): bool` | Si un parent existe encore |
| `restore(TrashItem $item, ?ResourceReference $parent): object` | Ramène la ressource sous ce parent, sans flush, et la retourne |
| `purge(TrashItem $item): void` | Oublie ce que la ressource a laissé hors de la corbeille et de ses tables, ses fichiers ; souvent rien |

La corbeille fait un seul flush, après le gestionnaire : un gestionnaire ne fait jamais de flush et ne supprime jamais
un fichier avant `purge()`.

## Événements

| Événement | Quand | Ce que fait un écouteur |
|---|---|---|
| `Phil\TrashBundle\Application\Event\ResourceTrashed` | Une ressource entre dans la corbeille | Écrit la piste d'audit, vide un cache |
| `Phil\TrashBundle\Application\Event\ResourceRestored` | Elle revient | La même chose |
| `Phil\TrashBundle\Application\Event\TrashItemPurged` | Elle est oubliée pour de bon | La même chose |

Ils sont déclenchés par le répartiteur d'événements de l'application, quand il y en a un. Voir
[Conservation](retention.md#événements).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `retention` | Combien de jours une ressource mise à la corbeille est gardée avant sa purge |
| `retention_by_kind` | Les jours gardés pour certains types : `{ page: 90 }` |

## `TrashItemRepository`

`Phil\TrashBundle\Domain\Repository\TrashItemRepository` : la corbeille, un alias public de
`DoctrineTrashItemRepository`. Un stockage propre à l'application borne `expired()` par sa limite, pour que
`phil:trash:purge` ne lise jamais toute la corbeille.
