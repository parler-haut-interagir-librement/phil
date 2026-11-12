<!-- phil-dev source-sha256=49b0247da1c9205cabb8496be840291ee9ae728c892d4f44e76d4b3a1d536a17 -->
# Référence

Tous les types se trouvent dans `Phil\IpAnonymizer`.

## Adresses

| Type | Rôle |
|---|---|
| `IpAddress` | Une adresse IPv4 ou IPv6 sous forme de ses octets ; `parse()`, `tryParse()`, `fromBytes()`, `equals()`, `toString()`. |
| `IpFamily` | `V4` ou `V6`, avec `bits()`. |
| `IpNetwork` | Une adresse dont les bits d'hôte sont effacés, avec une longueur de préfixe ; `containing()`, `parse()`, `contains()`, `equals()`, `toString()`. |
| `AddressScope` | La portée à usage particulier d'une adresse. |
| `ScopeClassifier` | `scopeOf()` : l'`AddressScope` d'une adresse. |

## Stratégies

| Type | Rôle |
|---|---|
| `Truncation` | Efface les bits d'hôte au-delà d'un préfixe propre à chaque famille ; aussi un `IpTransformer`. |
| `KeyedHash` | L'empreinte BLAKE2b d'une adresse avec le sel courant comme clé ; `digest()` donne l'`IpDigest`. |
| `IpDigest` | L'identifiant du sel et les octets d'une empreinte ; `equals()` en temps constant, `toString()`. |
| `NetworkGeneralisation` | Le réseau d'une adresse en notation CIDR ; `network()` donne l'`IpNetwork`. |
| `ScopeGeneralisation` | La portée d'une adresse sous forme de son libellé. |
| `LabelGeneralisation` | Le libellé d'un réseau qui contient l'adresse, sinon une stratégie de repli. |
| `NetworkLabels` | Une table de réseaux et de libellés, consultée par plus long préfixe. |
| `Pipeline` | Des transformateurs dans leur ordre, puis une stratégie. |
| `PerFamily` | Une stratégie pour IPv4 et une autre pour IPv6. |

## Sels

| Type | Rôle |
|---|---|
| `Salt` | Des octets secrets et un identifiant ; `of()`, `random()`. |
| `ForgottenSalt` | Un sel aléatoire par période d'une horloge, partagé par un `ExpiringSaltStore` et oublié à la fin de la période. Le fournisseur à privilégier. |
| `FixedSalt` | Toujours le même sel. |
| `RotatingSalt` | Un sel par période d'une horloge, dérivé d'une clé maîtresse ; facultatif, pour le cas où rien ne peut être stocké. |

## Contrats

Dans `Phil\IpAnonymizer\Contract` : `IpAnonymizer`, `IpTransformer`, `SaltProvider`, `ExpiringSaltStore`,
`NetworkLabeller`. Voir [Points d'extension](extension-points.md).

## Exceptions

Dans `Phil\IpAnonymizer\Exception` : `IpAnonymizerException`, `InvalidIpAddress`, `InvalidNetwork`, `InvalidSalt`,
`InvalidOption`. Voir [Exceptions](exceptions.md).
