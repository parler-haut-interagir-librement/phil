<!-- phil-dev source-sha256=da9c73647efe3754deaa531da3ca2ad3e220eec9357d46df89d2e4de50302612 -->
# Polices et médias

## Les polices de `phil/fonts-bundle`

```php
$book = $epubFonts->embed($book, 'Example Serif', 'Example Sans');
```

`embed(Book $book, string ...$families)` ajoute, pour chaque face de chaque famille du répertoire de polices (sans tenir
compte de la casse), un fichier et une règle `@font-face` :

- Le fichier est le plus petit des formats de la face, dans l'ordre WOFF2, WOFF, OpenType puis TrueType. Les systèmes de
  lecture lisent WOFF2 même là où PHP ne le peut pas : un fichier `.woff2` est donc incorporé à côté d'un fichier `.ttf`
  voisin lu sans `ext-brotli`. Il est placé sous `fonts.directory`, avec un chemin composé uniquement de lettres, de
  chiffres, de `.`, `_`, `-` et `/`, et il est obscurci lorsque `fonts.obfuscate` est activé.
- Les métadonnées de la face viennent du premier de ses fichiers qui a été lu ; une famille dont aucun fichier n'a pu
  être lu lance `FontNotEmbeddable`.
- Une face dont la licence interdit l'incorporation (`OS/2` restreint), ou n'autorise que les bitmaps, lance
  `FontNotEmbeddable` avant l'écriture du livre. L'obscurcissement ne rend pas incorporable une police restreinte.
- Les règles vont dans une seule feuille de style, à `fonts.stylesheet`, que la bibliothèque lie à chaque document de
  contenu. Leurs URL sont relatives à cette feuille et elles n'ont aucune source `local()`, pour que le système de
  lecture utilise le fichier incorporé.

Les collections sont exclues. Appelez `embed()` une seule fois par livre : un second appel ajoute une seconde feuille de
style au même chemin, ce que la bibliothèque refuse.

## Les médias de `phil/media-bundle`

| Méthode | Retourne |
|---|---|
| `image(Media $media, ?string $path)` | Une ressource image du fichier du média, à `images/<id>-<file name>` par défaut |
| `cover(Media $media, ?string $path)` | Une `Cover` de cette image avec le texte de remplacement du média ; un média qui n'en a pas lance l'`InvalidBook` de la bibliothèque |
| `font(Media $media, bool $obfuscate, ?string $path)` | Une ressource police, à `fonts/<id>-<file name>` par défaut |

La bibliothèque vérifie le type de média de chaque fichier par rapport aux types de médias de base d'EPUB.
