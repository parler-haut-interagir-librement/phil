<!-- phil-dev source-sha256=f506293a00c2743c7bba287678e8841fc78b4d43a04537000f9bbe061f147075 -->
# Points d'extension

Ce que l'application implémente, configure ou utilise. Les références sont celles de `phil/resource`.

## Ports

### `ReferenceCollector`

`Phil\ReferencesBundle\Application\ReferenceCollector` trouve vers quoi pointe un genre de source. Un service qui
l'implémente reçoit l'étiquette `phil_references.collector` par autoconfiguration. Voir
[Collecteurs et cibles](collectors.md).

| Méthode | Contrat |
|---|---|
| `supports(object $source): bool` | S'il lit cette source |
| `sourceOf(object $source): ResourceReference` | La référence propre de la source, lue avant qu'une suppression vide son identifiant |
| `collect(object $source): iterable<Link>` | Chaque lien, avec le champ où il se trouve : une propriété, ou un chemin dans un document JSON |

### `ReferenceTarget`

`Phil\ReferencesBundle\Application\ReferenceTarget` représente un genre de ressource qu'utilisent les autres. Un
service qui l'implémente reçoit l'étiquette `phil_references.target` par autoconfiguration. Sans cible, un genre ne
voit jamais une suppression refusée ni ses liens rompus vérifiés.

| Méthode | Contrat |
|---|---|
| `kind(): string` | Le genre qu'elle connaît |
| `exists(string $id): bool` | Si cette ressource existe encore |
| `referenceOf(object $resource): ?ResourceReference` | La référence d'un objet de son genre, `null` pour un autre objet |

### `UsageRepository`

`Phil\ReferencesBundle\Domain\Repository\UsageRepository` est la table des usages, un alias public de
`DbalUsageRepository` sur `connection`. Un magasin propre à l'application garde `replace()`, qui n'écrit que ce qui a
changé, et `usagesOfAll()`, qui lit plusieurs cibles à la fois.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `connection` | La connexion Doctrine qui garde les usages, et dont les entités sont suivies |
| `track_entities` | Si les usages des entités Doctrine sont tenus à jour à chaque *flush* |
| `block_deletion` | Si la suppression d'une entité encore utilisée interrompt le *flush* |

## Services

`References` — `usagesOf()`, `assertUnused()`, `broken()`, `refresh()`, `forget()` — est ce qu'appelle
l'application : avant une suppression, pour montrer un message plutôt qu'un *flush* annulé, et pour les sources qui ne
sont pas des entités Doctrine. Voir [Usages](usages.md).
