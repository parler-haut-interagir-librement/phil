<!-- phil-dev source-sha256=ff31d18247be8e430a92e3e4762168c08fde689acc0a407ba547cb6f6f43e7c1 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\AccessibilityCheckException`, qui étend `Throwable` :
attrapez-la pour les attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidPolicy` | `InvalidArgumentException` | une limite inférieure à 1, une couleur par défaut qui n'est pas une couleur CSS opaque, une couleur de jeton illisible, deux règles de même identifiant |
| `LimitExceeded` | `RuntimeException` | un contenu plus grand, plus profond ou comptant plus d'éléments ou de blocs que la politique ne le permet |
| `InvalidContent` | `RuntimeException` | du HTML qui n'est pas en UTF-8 |

## Messages

| Fabrique | Message |
|---|---|
| `InvalidPolicy::limit($name, $value)` | `The maximum size must be at least 1; got 0.` |
| `InvalidPolicy::colour($name, $value)` | `The foreground must be an opaque CSS colour; got "var(--text)".` |
| `InvalidPolicy::duplicateRule($id)` | `Two rules have the identifier "image-alt"; an identifier names one rule.` |
| `LimitExceeded::size($bytes, $limit)` | `The content holds 6000000 bytes; at most 5242880 are checked.` |
| `LimitExceeded::depth($limit)` | `The content nests deeper than 256 levels.` |
| `LimitExceeded::count($limit)` | `The content holds more than 100000 elements or blocks.` |
| `InvalidContent::notUtf8()` | `The content is not valid UTF-8.` |

La contrainte transforme `LimitExceeded` et `InvalidContent` en violation, la garde de publication en bloqueur et la
commande en code de sortie 2.
