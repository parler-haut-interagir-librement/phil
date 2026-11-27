<!-- phil-dev source-sha256=8a102254c882f76c7b4141f6ceb836b94156d72dc55ec70ec6c4a2da9005ccb2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/avatar` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `AvatarGenerator`, qui dessine un avatar comme document SVG ou URI `data:` à partir d'un germe, les mêmes octets pour
  le même germe et les mêmes options, étiqueté pour les technologies d'assistance ou caché pour elles.
- `AvatarOptions` : style, taille de 16 à 1024 pixels, contour, palette, nom, libellé et nombre de lettres.
- `Seed`, un identifiant opaque qui refuse une adresse courriel, et `Random`, les choix tirés de lui par SHA-256.
- `Initials`, par grappe de graphèmes, mises en capitale une pour une, le premier caractère seul pour les noms CJK, de
  droite à gauche pour l'arabe et l'hébreu.
- `Color` et `Palette`, avec la luminance relative et le contraste du WCAG ; chaque arrière-plan lisible à 4,5:1 avec
  l'une des encres ; les palettes `default`, `pastel`, `vivid`, `earth` et `grayscale`.
- `Canvas`, qui écrit lui-même chaque élément d'un avatar à partir de nombres, de couleurs et de texte échappé, et
  `Shape`.
- Le port `AvatarStyle` et les styles `InitialsStyle`, `ShapesStyle` et `PixelsStyle`.
- `AvatarException`, implémentée par `InvalidAvatar`.
- Une cible de fuzzing pour les initiales et les avatars dessinés à partir d'elles, et un banc d'essai de chaque style.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
