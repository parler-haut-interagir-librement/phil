<!-- phil-dev source-sha256=1c3e50a396775a6e600d6854f256447fcef6b652d9eca7814e80eb3adcd05591 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/creation-disclosure` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Ajouté

- `DigitalSourceType::XMP_NAMESPACE`, l'espace de noms du schéma IPTC Extension que représente le préfixe de
  `DigitalSourceType::XMP_PROPERTY`, pour écrire la propriété dans un paquet XMP.
- La documentation du marquage des médias générés et modifiés : ce que porte un fichier, et pourquoi le type est récrit
  dans les fichiers dérivés d'un fichier déclaré.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `DigitalSourceType` : le vocabulaire IPTC Digital Source Type, ses dix-sept codes actuels et ses trois codes retirés,
  lu d'un code ou d'une adresse en `http` ou en `https`, avec son adresse, son libellé anglais, son remplaçant quand il
  est retiré, et ce qu'il dit de l'IA générative.
- `Part` : les parties d'une œuvre déclarées seules — texte, image, audio, vidéo, code.
- `CreationDisclosure` : comment une œuvre a été faite, partie par partie, immuable ; les parties non déclarées gardées
  visibles ; un type retiré déclaré comme son remplaçant ; la vue de l'IA générative d'une partie et de toute l'œuvre,
  la vue sans IA ; le stockage comme tableau de codes et la relecture, adresses comprises.
- `GenerativeAi` : la vue — aucune, non déclarée, possible, partielle, entière —, sa combinaison sur les parties et son
  ordre.
- `DisclosureRequirement` : les parties qu'un concours ou une boutique demande de déclarer, avec un plafond facultatif
  d'IA générative, et les parties non satisfaites d'une participation.
- `Exception\CreationDisclosureException` et `Exception\InvalidDisclosure`, dont les messages montrent au plus 80
  caractères de ce qui a été donné.
- Une cible de fuzzing pour `CreationDisclosure::fromArray()`.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, et son miroir français.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
