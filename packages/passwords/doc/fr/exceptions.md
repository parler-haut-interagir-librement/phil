<!-- phil-dev source-sha256=1c04663e70e7270ac9c4c41db4f44e724e9015efb4b0f49818509f4d318cf492 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\PasswordsException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidPolicy` | `InvalidArgumentException` | Une `PasswordPolicy` ou une `PassphrasePolicy` hors bornes : une longueur ou un nombre de mots, aucun ensemble de caractères, un séparateur trop long ou contenant une lettre. |
| `InvalidWordlist` | `InvalidArgumentException` | Une liste de moins de 256 mots, ou avec un mot vide, trop long, répété ou contenant une espace ; une langue fournie inconnue. |
| `InvalidDictionary` | `InvalidArgumentException` | Un dictionnaire sans nom, une entrée vide ou de plus de 64 caractères, un dictionnaire fourni inconnu. |
| `BreachCheckUnavailable` | `RuntimeException` | Une source de fuites qui ne peut pas répondre : réseau, statut, délai, taille, réponse mal formée. `BreachChecker` l'attrape. |

L'estimateur et les générateurs ne lancent rien d'autre : toute chaîne est un mot de passe que l'estimateur lit.
