<!-- phil-dev source-sha256=73b48a22c98dba5f83a19037caab40716554a71b3b5125e38cf4a99f1cd5848e -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\PasswordsException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidPolicy` | `InvalidArgumentException` | Une `PasswordPolicy` ou une `PassphrasePolicy` hors bornes : une longueur ou un nombre de mots, aucun ensemble de caractères, un séparateur trop long ou contenant une lettre. Depuis `PassphraseGenerator::generate()` et `entropyBits()`, une politique dont les phrases de passe ne se relisent pas sur la liste du générateur : un chiffre ajouté à un mot qui écrit un autre mot, ou des mots qui se fondent avec le séparateur ([Générer des mots de passe](generating.md#phrases-de-passe-qui-se-relisent)). |
| `InvalidWordlist` | `InvalidArgumentException` | Une liste de moins de 256 mots, ou avec un mot vide, trop long, répété ou contenant une espace ; une langue fournie inconnue. |
| `InvalidDictionary` | `InvalidArgumentException` | Un dictionnaire sans nom, une entrée vide ou de plus de 64 caractères, un dictionnaire fourni inconnu. |
| `BreachCheckUnavailable` | `RuntimeException` | Une source de fuites qui ne peut pas répondre : réseau, statut, délai, taille, réponse mal formée. `BreachChecker` l'attrape. |

L'estimateur et les générateurs ne lancent rien d'autre : toute chaîne est un mot de passe que l'estimateur lit.
