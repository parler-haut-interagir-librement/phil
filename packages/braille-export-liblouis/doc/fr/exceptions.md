<!-- phil-dev source-sha256=707bdf003f523f19458839352c913a6d1a5f7df1df35ba8078d0fda23161bc24 -->
# Exceptions

Chaque exception du traducteur étend `Exception\LiblouisException`, qui implémente `TranslationFailed` de
`phil/braille-export`. Elle porte le code avec lequel le programme s'est terminé, `$exitCode`, nul quand il ne s'est
pas terminé de lui-même ou n'a pas démarré, et les 64 premiers Kio de sa sortie d'erreur, `$errorOutput`.

| Exception | Quand |
|---|---|
| `ExecutableNotFound` | Le programme n'est pas dans le `PATH`, ou son chemin n'est pas un fichier exécutable. |
| `TableNotFound` | liblouis ne trouve pas une table qu'on lui a demandée : sa sortie d'erreur dit qu'il ne peut la résoudre, la trouver ou l'ouvrir. |
| `LiblouisFailed` | Le programme s'est arrêté avec un autre code de sortie, a manqué de temps, ne s'est pas rendu à sa fin, a écrit plus que sa limite, a rendu un autre nombre de lignes ou des caractères qui ne sont pas du braille, ou n'a pas nommé sa version ; le lot dépassait sa limite. |

`UnsupportedTranslation` de `phil/braille-export` dit qu'aucune table n'est fixée pour la langue et la contraction.
`InvalidArgumentException` arrête un traducteur ou un ensemble de tables construit avec une valeur hors de ses bornes.
