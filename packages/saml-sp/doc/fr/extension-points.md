<!-- phil-dev source-sha256=af2d3cb97080e9401751dbe8046540e3faa12f747631a008e44e3f750dc0d224 -->
# Points d'extension

La bibliothèque est dans ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que le CHANGELOG indique. Ils sont destinés à être stables à partir de `1.0`.

## Ports

| Port | Implémentations | Remplacez-le pour |
|---|---|---|
| `Contract\ReplayGuard` | `DbalReplayGuard` (Doctrine DBAL), `CacheReplayGuard` (PSR-6), `InMemoryReplayGuard` (tests) | garder les assertions acceptées dans un autre magasin |
| `Contract\PendingRequests` | `CachePendingRequests` (PSR-6), `InMemoryPendingRequests` (tests) | garder les requêtes en attente ailleurs |
| `Contract\MetadataFetcher` | `SafeHttpMetadataFetcher` | récupérer les métadonnées par un mandataire, ou depuis un magasin |

Un `ReplayGuard` répond `true` une seule fois par émetteur et ID d'assertion jusqu'à l'expiration qu'on lui donne, sur
chaque *worker* et chaque serveur. `DbalReplayGuard` décide par une insertion atomique sur la clé primaire de sa table ;
`CacheReplayGuard` lit puis écrit : deux copies d'une même assertion postées au même instant à deux serveurs pourraient
passer toutes les deux. Voir [Le magasin de rejeu](replay-store.md).

## Options

- `SecurityPolicy` : le décalage d'horloge, l'âge d'une réponse, la `SignaturePolicy` (SHA-1, la plus petite clé RSA) et
  le `XmlBudget` d'un message.
- `IdentityProvider` : la table des attributs, `wantAssertionsSigned`, `wantAssertionsEncrypted`, `allowUnsolicited`, la
  liaison des requêtes.
- `ServiceProvider` : l'identifiant d'entité, les services, les paires de clés, le format de l'identifiant de nom, si
  les requêtes sont signées.
- `AttributeMap` : des noms quelconques par champ ; `with()` en change un.
- `MetadataReader` : la politique de signature, le budget, `allowLocalhost`.
- `MetadataLoader` : le récupérateur, le cache et sa durée de vie.
