<!-- phil-dev source-sha256=d5cce673226cc42abf5abca41674c71fd1045efc60e71b8a5f74f646c69613cf -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/publishing` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `PagePublished` et `PageRetired` : une page qui passe en ligne ou hors ligne, avec son adresse canonique (une
  `Phil\Url\Url`), sa langue comme étiquette BCP 47, un drapeau « indexable » et l'identifiant que lui donne le paquet
  qui la possède ; immuables, vérifiés à la construction.
- `Contract\PageEvent`, les quatre champs en lecture seule que portent les deux événements.
- `Exception\InvalidPageEvent` : une langue qui n'est pas une étiquette BCP 47 de 35 caractères au plus, ou un
  identifiant de propriétaire vide, plus long que 255 octets, ou qui contient un caractère de contrôle ou des espaces
  autour.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
