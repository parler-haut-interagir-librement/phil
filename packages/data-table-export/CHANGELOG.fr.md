<!-- phil-dev source-sha256=20a49b8c53c2d3ce5f5490114fcd3abbf1c92777fe8f2471c2833f39df93d22f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/data-table-export` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Sheet`, un titre, des en-têtes et des rangées lues une fois de n'importe quel itérable ; ses cellules ne contiennent
  rien, des booléens, des nombres, des textes, des dates, des enums adossés ou des `Stringable`, tout le reste étant
  refusé avec `Exception\ExportRefused`.
- `Format`, CSV et XLSX, lu d'un nom, avec le type de média et un nom de fichier sûr.
- `Contract\ExportWriter`, et `ExportWriters`, un rédacteur par format, CSV et XLSX par défaut.
- `Csv\CsvWriter`, la RFC 4180 écrite rangée par rangée : un séparateur au choix, la marque d'ordre des octets UTF-8, et
  `Csv\FormulaGuard`, qui garde comme texte un texte qu'un tableur exécuterait comme formule et laisse tranquilles les
  nombres signés.
- `Xlsx\XlsxWriter`, un classeur d'une feuille écrit avec ext-zip et du XML par morceaux dans des fichiers temporaires
  toujours retirés : des chaînes en ligne qui ne s'exécutent jamais, des nombres, des booléens et des dates typés, des
  en-têtes en gras figés, un nom de feuille sûr, les limites d'un tableur et une limite de rangées plus basse, les mêmes
  octets pour la même feuille.
- La cible de fuzzing du texte d'une cellule et les bancs d'essai des deux formats.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
