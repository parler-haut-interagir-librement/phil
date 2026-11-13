<!-- phil-dev source-sha256=4dd910c26058e20c3ba7a439f9476c0c7c2c259809d944bb5a8c3c4493b1477c -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\AccessCodes\Exception\AccessCodesException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidCode` | `InvalidArgumentException` | `CodeFormat::parse()` refuse un texte : trop long, mauvaise longueur, symbole étranger, mauvais symbole de contrôle. `redeem()` la transforme en `Unknown` |
| `InvalidConfiguration` | `InvalidArgumentException` | Une valeur avec laquelle la bibliothèque ne peut pas travailler : un alphabet, un format, une clé, un lot, une personne qui échange, un lien, un nom de table, un code stocké deux fois |
| `CodesAlreadyTaken` | `LogicException` | Les codes d'un lot créé sont demandés une seconde fois |
| `UnknownBatch` | `RuntimeException` | `batch()`, `counts()` ou `revoke()` nomme un lot que le magasin ne contient pas |

Aucun message ne répète un code ou une clé.
