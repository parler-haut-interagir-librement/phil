<!-- phil-dev source-sha256=5bdb2ef7740eb3f9a3151cd62715b882f6aa38e7d41b70c80faec702f2228a8a -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise. Les blocs sont lus dans des documents UJO (Universal JSON
Output).

## Ports écrits par l'application

Chacun est nommé par son identifiant de service dans la configuration — voir [Configuration](configuration.md). Les
points de terminaison répondent `404` tant que `holder_provider` et `policy` ne sont pas tous deux définis.

### `HolderProvider`

`Phil\EditLockBundle\Application\HolderProvider` — qui écrit dans la requête courante (`holder_provider`).

| Méthode | Contrat |
|---|---|
| `holder(): ?Holder` | Le rédacteur courant, un identifiant et le nom montré aux autres, ou `null` pour personne |

### `EditPolicy`

`Phil\EditLockBundle\Application\EditPolicy` — ce qu'un rédacteur peut modifier et reprendre (`policy`). Sans politique,
`EditLocks` utilise `DefaultEditPolicy` — tout le monde peut modifier, personne ne peut reprendre — pour le propre code
de l'application. Voir [Baux](leases.md).

| Méthode | Contrat |
|---|---|
| `mayEdit(Holder $holder, string $resource): bool` | Protège la prise, la reprise, les pulsations, l'écriture des blocs et la lecture de la présence |
| `mayTakeOver(Holder $holder, Lease $lease): bool` | Interrogée pour chaque bail qu'une reprise retirerait |

### `BlockStore`

`Phil\EditLockBundle\Application\BlockStore` — l'endroit où les blocs enregistrés sont conservés (`block_store`). Sans
magasin, les enregistrements sont seulement communiqués aux autres rédacteurs. Voir [Blocs](blocks.md).

| Méthode | Contrat |
|---|---|
| `save(string $resource, Block $block, string $list, ?string $after, Holder $holder, Fence $fence): void` | Place le bloc dans le brouillon de la ressource, à la place de sa copie ou après `$after` dans `$list` ; appelée dans la revendication qui a vérifié le bail, avant que l'enregistrement soit communiqué aux autres |
| `remove(string $resource, string $blockId, Holder $holder, Fence $fence): void` | Retire le bloc du brouillon, dans la revendication qui a vérifié le bail |

Un magasin qui écrit sur la connexion des baux Doctrine écrit dans la transaction de la revendication. Un magasin qui
peut appliquer une écriture plus tard conserve `$fence->number` avec le bloc et refuse une écriture dont le numéro n'est
pas plus grand, par une écriture conditionnelle dont il vérifie le nombre de lignes ; `Fence::isNewerThan()` compare
deux clôtures ([Les écritures sous un bail](blocks.md#les-écritures-sous-un-bail)).

## Ports avec des adaptateurs fournis

### `Broadcaster`

`Phil\EditLockBundle\Application\Broadcaster` — communique aux autres rédacteurs ce qui a changé. `MercureBroadcaster`
avec MercureBundle, `NullBroadcaster` sans lui ; une application fait pointer l'alias vers le sien — un autre service de
poussée.

| Méthode | Contrat |
|---|---|
| `presence(string $resource, list<Lease> $leases, DateTimeImmutable $now): void` | Les baux actifs sur la ressource, après la modification de l'un d'eux |
| `saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void` | Un bloc enregistré, sous forme de document de ce seul bloc |
| `removed(string $resource, Holder $holder, string $blockId): void` | Un bloc retiré |

### `LeaseRepository`

`Phil\EditLockBundle\Domain\Repository\LeaseRepository` — les baux ; Doctrine ou mémoire, selon `store`. Un magasin
propre à l'application conserve les garanties de chaque méthode : `exclusively()` exécute une revendication pendant
qu'aucune autre revendication sur la ressource ne s'exécute, `fence()` répond dans celle-ci un numéro plus grand que
celui de toute revendication antérieure sur la ressource, `insert()` conserve un bail seulement si aucun n'est sur sa
clé, `replace()` seulement si la version lue est encore celle qui est conservée, `delete()` seulement pour le détenteur
qui le détient.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `seconds` | La durée d'un bail depuis sa dernière pulsation |
| `mercure.hub`, `topic_prefix`, `private` | Le hub, les sujets et la confidentialité qu'utilisent les mises à jour — voir [Mercure](mercure.md) |

## Le navigateur

| Point | Comment |
|---|---|
| Les libellés | `messages` dans la configuration du contrôleur, par clé : `presence`, `editing`, `takeOver` et les autres |
| L'apparence | `assets/styles/edit-lock.css` sous `.phil-edit-lock-roster`, avec `--pel-text`, `--pel-accent` et les autres |
| L'éditeur de blocs | Le contrôleur répartit `phil-block-editor:apply`, `phil-block-editor:remove` et `phil-block-editor:lock` sur l'élément de l'éditeur ; tout éditeur qui les écoute fonctionne avec lui |

Voir [JavaScript](javascript.md).
