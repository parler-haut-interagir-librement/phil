<!-- phil-dev source-sha256=80d6eda2c65b2999d61be6acd37484e54d15e26e85bf5c40553e82ef3546a7cd -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/address` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Address`, `AddressField`, `AddressFormat`, `AddressFormats`, `Countries`, `Subdivision`, `Subdivisions`,
  `AddressValidator`, `AddressViolation`, `Violation`, `AddressFormatter`, `AddressArray` ; `AddressException`,
  `InvalidAddress`, `InvalidData` et `UnreadableAddress`. `Subdivisions::find()` cherche une valeur dans un index de sa
  liste, construit une fois, par les clés que donne `Subdivision::keys()`, et `AddressFormat` lit les champs de son
  format une fois, à sa construction, pour `fields()` et `uses()`.
- `SchemaOrg` : la forme schema.org de la valeur de la bibliothèque, à intégrer dans du JSON-LD.
- Les formats et les subdivisions de commerceguys/addressing v2.3.2 dans `data/`, chaque fichier commençant par
  `declare(strict_types=1);` comme l'écrit la norme de codage du dépôt, avec `NOTICE`.
- `Data\DataImporter` et `bin/import-data.php` : chaque fichier est écrit dans un dossier de préparation avant que
  `formats/` et `subdivisions/` soient remplacés, si bien que des données refusées à mi-chemin laissent intactes les
  données en place.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
