<!-- phil-dev source-sha256=16783802167e4ed295f0bf099b80ea21b8852716badcd4bdde4ec7ac37601021 -->
# Le catalogue

## Lire un fichier

`FontFiles` lit les fichiers de police avec le décompresseur Brotli dont PHP dispose. Le service est construit par
`FontFiles::withAvailableBrotli()`, qui passe `brotli_uncompress()` quand `ext-brotli` est chargée ; `readsWoff2()`
indique si c'est le cas.

| Méthode | Retourne |
|---|---|
| `read(string $path)` | Le `FontFile` d'un chemin |
| `readBytes(string $bytes, string $name)` | Le `FontFile` d'une suite d'octets, nommé pour le message d'un fichier illisible |
| `readsWoff2()` | Si les fichiers WOFF2 sont lus |

Un fichier illisible lance `FontUnreadable`. Son message nomme le fichier et donne la raison de la bibliothèque, et son
exception précédente est celle de la bibliothèque. Un fichier WOFF2 sans `ext-brotli` indique
`The font file "Sans.woff2" cannot be read: A WOFF2 file compresses its tables with Brotli, which PHP does not decode
without ext-brotli: install ext-brotli and pass Brotli::decompressor(), or read the WOFF, TrueType or OpenType file of
the font.`

## Les fichiers du répertoire

`FontCatalogue` lit chaque fichier `.ttf`, `.otf`, `.ttc`, `.otc`, `.woff` et `.woff2` du répertoire configuré et de ses
dossiers, sans égard à la casse de l'extension, la première fois qu'on le lui demande. Il les conserve pour le reste de
la requête ou de la commande. Un répertoire qui n'existe pas ne contient aucune police.

`entries()` donne un `CatalogueEntry` par fichier, trié par chemin :

| Propriété ou méthode | Contenu |
|---|---|
| `path` | Le chemin relatif au répertoire, avec `/` entre les dossiers |
| `absolutePath` | Le chemin sur le disque |
| `format` | Le `Format` du fichier une fois lu ; celui qu'annonce son extension s'il n'a pas pu être lu |
| `file` | Le `FontFile`, ou null |
| `error` | La raison pour laquelle le fichier n'a pas pu être lu, ou null |
| `isReadable()` | Si `file` est présent |
| `stem()` | Le chemin sans son extension : les fichiers d'une même face en plusieurs formats le partagent |

`unreadable()` liste les entrées qui n'ont pas pu être lues. `get($path)` donne une entrée ou lance `FontNotFound`.

## Trouver une face

```php
$entry = $catalogue->find('Example Sans', 700, FontStyle::Italic, Format::Woff2, Format::TrueType);
```

`find()` donne le premier fichier, par chemin, qui contient une face de la famille (sans égard à la casse), de la
graisse (n'importe où dans la plage de l'axe `wght` d'une police variable) et du style demandés. Avec des formats, il
les cherche dans leur ordre et donne le premier qui contient la face ; null si aucun ne la contient. `require()` prend
les mêmes arguments et lance `FontNotFound` au lieu de retourner null. `face()` donne le `Font` lui-même, la face d'une
collection parmi les autres. Les fichiers qui n'ont pas pu être lus ne sont jamais trouvés.

## Fiches de licence

`licences(LicenceRegister $kept)` donne un registre des fichiers qui ont été lus. Un fichier illisible garde la fiche de
`$kept`, s'il en a une : un fichier WOFF2 inscrit là où `ext-brotli` est chargée n'est pas signalé comme retiré là où
elle ne l'est pas. [Licences](licences.md) l'utilise.
