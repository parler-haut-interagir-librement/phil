<!-- phil-dev source-sha256=0a8eefc2d7b11f5e3ebb2aef97a5f8387a579cdcad009f5fd8d9fe886b09ab36 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/spam-protection` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Inspection\Signal` et `Inspection\Inspection`.
- `Token\SubmissionToken` et `Token\TokenCodec` ; l'appelant compare la portée du jeton.
- `Timing\TimingPolicy` et `Proof\ProofOfWork`.
- `Content\ContentRules`, qui lève `Signal::Malformed` pour un texte qui n'est pas de l'UTF-8 valide et le lit avec
  chaque mauvaise séquence remplacée, plutôt que de lancer une exception ; borne le texte qu'il lit (`maximumLength`, 20
  000 caractères par défaut) et lève `Signal::TooLong` au-delà ; compte une adresse `https://www.` comme un seul lien,
  et chacune de plusieurs adresses collées par une virgule ou un guillemet ; et reconnaît un terme interdit de plusieurs
  mots, ou avec de la ponctuation entre ses mots, comme ces mots à la suite.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, dont le démarrage rapide traite un jeton
  manquant ou forgé, compare la portée du jeton et vérifie la preuve.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
