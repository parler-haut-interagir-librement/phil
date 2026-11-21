<!-- phil-dev source-sha256=b5c4e1f2a6a941d129ef564caef480c3dc0e0ae41baf1c35321c575cb4fca91f -->
# Exceptions

Chaque exception du traducteur étend `Exception\LiblouisException`, qui implémente `TranslationFailed` de
`phil/braille-export`. Elle porte le code avec lequel le programme s'est terminé, `$exitCode`, nul quand il ne s'est
pas terminé de lui-même ou n'a pas démarré, et les 64 premiers Kio de sa sortie d'erreur, `$errorOutput`.

| Exception | Quand |
|---|---|
| `ExecutableNotFound` | Le programme n'est pas dans le `PATH`, ou son chemin n'est pas un fichier exécutable. |
| `TableNotFound` | liblouis ne trouve pas une table qu'on lui a demandée : sa sortie d'erreur dit qu'il ne peut la résoudre, la trouver ou l'ouvrir. |
| `OutdatedLiblouis` | Le programme annonce un liblouis antérieur à 3.39.0, refusé avant l'envoi de tout texte ; `$allowOutdatedLiblouis` en fait un avertissement. |
| `LiblouisFailed` | Le programme s'est arrêté avec un autre code de sortie, a manqué de temps, ne s'est pas rendu à sa fin, a écrit plus que sa limite, a rendu un autre nombre de lignes ou des caractères qui ne sont pas du braille, ou n'a pas nommé sa version, ce que `$allowOutdatedLiblouis` change en avertissement avant un lot ; le lot dépassait sa limite. |

`UnsupportedTranslation` de `phil/braille-export` dit qu'aucune table n'est fixée pour la langue et la contraction.
`InvalidArgumentException` arrête un traducteur ou un ensemble de tables construit avec une valeur hors de ses bornes.
