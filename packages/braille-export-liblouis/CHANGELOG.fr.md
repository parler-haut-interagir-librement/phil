<!-- phil-dev source-sha256=524fb2079cb8bed1685439963c7414889984e46596c4b2beabbc2ad03effe0b6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/braille-export-liblouis` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-24

Un liblouis plus ancien que 3.39.0 est refusé par défaut.

### Ajouté

- `LiblouisTranslator::MINIMUM_VERSION`, 3.39.0, et les arguments `allowOutdatedLiblouis` et `logger` de
  `LiblouisTranslator` : un liblouis périmé, ou dont la version ne peut pas être lue, traduit quand même avec un
  avertissement PSR-3, journalisé une fois.
- `Exception\OutdatedLiblouis`, qui nomme la version trouvée et la plus ancienne acceptée.

### Modifié

- `version()` interroge le programme une fois et garde sa réponse, avec le délai d'attente d'un lot, le chemin des
  tables, et une réponse de 4 Kio au plus.
- La suite d'intégration est sautée sur une machine dont le liblouis est plus ancien que 3.39.0.

### Sécurité

- Avant sa première traduction, `LiblouisTranslator` demande sa version à liblouis et refuse avec une `OutdatedLiblouis`
  une version plus ancienne que 3.39.0, qui corrige des défauts de sûreté de la mémoire, et avec une `LiblouisFailed`
  une réponse sans version : les textes qu'il traduit viennent de documents.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `LiblouisTranslator` : le `Translator` de `phil/braille-export` sur `lou_translate`, tout un lot en une exécution, un
  texte par ligne de son entrée standard, sa réponse lue comme braille Unicode par la table d'affichage `unicode.dis` et
  vérifiée, les longs textes envoyés en morceaux, les arguments donnés comme liste et jamais à un shell, un délai
  d'attente, des limites sur l'entrée et sur la sortie, un chemin des tables, la version lue, `isAvailable()` pour une
  vérification au déploiement.
- `LiblouisTables` : la table de chaque langue et de chaque abrégé, l'Unified English Braille et le Code braille
  français uniformisé par défaut, une région qui se rabat sur sa langue, les noms vérifiés.
- `LiblouisException` et ses exceptions : `ExecutableNotFound`, `TableNotFound`, `LiblouisFailed`, chacune avec le code
  de sortie et la sortie d'erreur bornée du programme.
- La suite d'intégration contre le vrai programme, sautée sans liblouis.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
