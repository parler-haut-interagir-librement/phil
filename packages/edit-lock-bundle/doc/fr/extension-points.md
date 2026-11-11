<!-- phil-dev source-sha256=df2fe87e01758079e193f5104150ab307a3c29158ed19a38aa89306b63c89367 -->
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
| `save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void` | Place le bloc dans le brouillon de la ressource, à la place de sa copie ou après `$after` dans `$list` ; appelée avant que l'enregistrement soit communiqué aux autres |
| `remove(string $resource, string $blockId, Holder $holder): void` | Retire le bloc du brouillon |

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
qu'aucune autre revendication sur la ressource ne s'exécute, `insert()` conserve un bail seulement si aucun n'est sur sa
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
