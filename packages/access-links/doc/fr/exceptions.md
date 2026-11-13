<!-- phil-dev source-sha256=e2f37b3a745d68497587d2944fb2f41a4e3d4a264264b973cdf5c749c4714f17 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\AccessLinks\Exception\AccessLinksException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidConfiguration` | `InvalidArgumentException` | Une valeur avec laquelle la bibliothèque ne peut pas travailler : une clé, une campagne, un destinataire, des moments dans le désordre, une réponse, un calendrier, une adresse, un nom de table, les seize liens atteints |
| `InvalidToken` | `InvalidArgumentException` | `LinkToken::parse()` refuse un texte ; `open()` et `respond()` la transforment en « n'ouvre rien » |
| `LinkNotUsable` | `RuntimeException` | `respond()` avec un lien qui n'ouvre rien : mal formé, inconnu, révoqué, pas encore valide ou expiré |
| `InvitationClosed` | `RuntimeException` | `respond()` après la clôture |
| `AlreadyInvited` | `RuntimeException` | `invite()` pour un destinataire déjà invité à la campagne |
| `UnknownInvitation` | `RuntimeException` | Un identifiant d'invitation que le magasin ne contient pas |

Aucun message ne répète un jeton ou une clé ; `LinkNotUsable` ne dit pas pourquoi un lien n'ouvre rien.
