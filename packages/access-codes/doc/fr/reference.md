<!-- phil-dev source-sha256=d20fee7ba74fc64d6d9ee243d33a3ae801332e37824f8614d978a512efa62726 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\AccessCodes\AccessCodes` | `final readonly class` | La bibliothèque en un objet : `create()`, `redeem()`, `revoke()`, `batch()`, `counts()` |
| `Phil\AccessCodes\CodeAlphabet` | `final readonly class` | Les symboles d'un code, et les symboles semblables lus comme eux |
| `Phil\AccessCodes\CodeFormat` | `final readonly class` | Longueur, entropie, symbole de contrôle et groupement ; tire, lit et affiche les codes |
| `Phil\AccessCodes\CodeHasher` | `final readonly class` | Le HMAC-SHA-256 d'un code sous une liste ordonnée de clés |
| `Phil\AccessCodes\Batch` | `final readonly class` | Un lot nommé : identifiant, nom, taille, création, expiration, révocation |
| `Phil\AccessCodes\BatchCounts` | `final readonly class` | Les codes d'un lot au total, échangés, révoqués et inutilisés |
| `Phil\AccessCodes\CreatedBatch` | `final class` | Un lot qui vient d'être créé, avec ses codes en clair pour un seul usage |
| `Phil\AccessCodes\RedemptionLinks` | `final readonly class` | L'adresse de la page d'échange avec un code rempli |
| `Phil\AccessCodes\Redemption` | `final readonly class` | La réponse à un échange |
| `Phil\AccessCodes\RedemptionOutcome` | enum | Échangé, inconnu, déjà échangé, expiré, révoqué, trop de tentatives |
| `Phil\AccessCodes\CodeState` | `final readonly class` | Ce qu'un magasin sait d'un code |
| `Phil\AccessCodes\Contract\CodeStore` | interface | Là où vivent les lots et les empreintes |
| `Phil\AccessCodes\InMemoryCodeStore` | `final class` | Les lots d'un seul processus |
| `Phil\AccessCodes\Dbal\DbalCodeStore` | `final readonly class` | Les lots dans deux tables d'une connexion Doctrine DBAL |
| `Phil\AccessCodes\Exception\AccessCodesException` | interface | Chaque exception de la bibliothèque |
| `Phil\AccessCodes\Exception\InvalidCode` | `final class` | Un texte qui n'est pas un code du format |
| `Phil\AccessCodes\Exception\InvalidConfiguration` | `final class` | Une valeur avec laquelle la bibliothèque ne peut pas travailler |
| `Phil\AccessCodes\Exception\CodesAlreadyTaken` | `final class` | Les codes d'un lot créé demandés deux fois |
| `Phil\AccessCodes\Exception\UnknownBatch` | `final class` | Un lot que le magasin ne contient pas |
