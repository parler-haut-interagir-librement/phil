<!-- phil-dev source-sha256=8ce671c2970f749583feb517eb8ce15809145a910b9f13a642d19a560163fb93 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Resource\ResourceReference` | `final readonly class` | Un genre et un identifiant |
| `Phil\Resource\InvalidResource` | `InvalidArgumentException` | Une référence qui enfreint les règles |

## Les règles

| Partie | Règle |
|---|---|
| `key`, le genre | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, en commençant par une lettre |
| `id`, l'identifiant | De 1 à 255 octets, tout le reste est libre, deux-points compris |

`toString()` écrit `kind:identifier` ; `fromString()` la relit : le premier deux-points termine le genre, qui n'en
contient aucun. `equals()` compare les deux parties.
