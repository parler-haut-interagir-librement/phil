<!-- phil-dev source-sha256=2e315692ee3404e2e16a3da535eb0d36714e10f163fcca8f2c9039d64b584ae6 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes readonly, les constantes typées, `array_find()`, `new` sans parenthèses |
| `ext-mbstring` | La conversion en UTF-8 des chaînes UTF-16 de la table `name` |
| `ext-zlib` | La décompression des tables et des métadonnées étendues des fichiers WOFF |
| Un décodeur Brotli, pour WOFF2 seulement | WOFF2 compresse ses tables avec Brotli, que PHP ne décode pas seul |

La bibliothèque n'a aucune autre dépendance : aucun binaire, aucun service, aucun fichier de police à elle.

## Installer

```sh
composer require phil/fonts
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Lisez un fichier là où ses métadonnées sont
nécessaires :

```php
use Phil\Fonts\FontFile;

$file = FontFile::fromFile($path);
```

## Brotli pour WOFF2

Chaque méthode de lecture prend un décompresseur comme dernier argument : une fermeture qui reçoit les octets compressés
et la longueur qu'ils doivent atteindre une fois décompressés, et qui retourne les octets, ou `false` lorsqu'elle n'y
arrive pas. L'extension PECL `brotli` en fournit un, `brotli_uncompress()`, que Composer suggère :

```sh
pecl install brotli
```

`Brotli::decompressor()` le fournit lorsque l'extension est chargée, et null sinon : le même appel lit donc WOFF2 là où
l'extension est installée, et tous les autres formats partout :

```php
use Phil\Fonts\Brotli;
use Phil\Fonts\FontFile;

$file = FontFile::fromFile($path, Brotli::decompressor());
```

`Brotli::decompressor('my_brotli_decode')` nomme une autre fonction qui prend les mêmes arguments. La bibliothèque
vérifie la longueur de ce que retourne le décompresseur, et passe la longueur attendue pour qu'un décodeur puisse
s'arrêter au-delà. Sans décompresseur, un fichier WOFF2 lance `UnsupportedFont` avec un message qui dit quoi faire :
installer `ext-brotli`, ou lire le fichier WOFF, TrueType ou OpenType de la police. Les autres formats ne l'appellent
jamais.

Un décodeur Brotli en PHP simple exigerait de fournir avec la bibliothèque le dictionnaire statique de 122 Ko de la RFC
7932, pour un seul format qu'un serveur peut aussi convertir à l'avance : la bibliothèque laisse ce travail à
l'extension, ou à un décodeur que l'application possède déjà.
