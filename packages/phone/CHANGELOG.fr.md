<!-- phil-dev source-sha256=70a65c814f1db2d528745fa1d64243ad5b45b521f6db68e4674c656f676a0d9d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/phone` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Ajouté

- `InvalidPhoneNumber::$input`, l'entrée coupée à 100 caractères, et `InvalidPhoneNumber::SHOWN_LENGTH`.

### Sécurité

- Les messages d'`InvalidPhoneNumber` contenaient le texte tapé, le plus souvent le vrai numéro : un message qui
  atteignait un journal y gardait des données personnelles. Ils ne nomment que la raison ; une région sans plan est
  encore nommée.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhoneNumber`, `PhoneNumberType`, `PhoneFormat` ; `PhoneException`, `InvalidPhoneNumber` et `InvalidMetadata`.
  `PhoneNumber::region()` et `type()` trouvent la région et le type en une passe, gardés tant que le numéro vit.
  `PhoneNumber::parse()` refuse une entrée plus longue que `PhoneNumber::MAX_INPUT`, 100 caractères, avant qu'aucun
  motif s'exécute sur elle, pour que son temps reste borné quoi qu'on envoie.
- `Metadata\Territory`, `NumberDesc`, `NumberFormat` et `Territories`. `Territories` construit la liste triée des pays
  une fois par processus et y trouve un pays par clé ; `NumberDesc` construit son motif une fois.
- Les plans de numérotation de libphonenumber v9.0.42 dans `data/`, chaque fichier commençant par
  `declare(strict_types=1);`, avec `NOTICE` et l'Apache License 2.0.
- `Metadata\MetadataImporter` avec `bin/import-metadata.php`. `MetadataImporter::import()` lit chaque territoire avant
  que rien soit écrit, renomme chaque fichier par-dessus l'ancien, et retire en dernier les plans abandonnés, si bien
  qu'une importation échouée laisse chaque plan entier, l'ancien ou le nouveau. Un fichier qui ne peut pas être écrit
  est une `InvalidMetadata::unwritable()`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
