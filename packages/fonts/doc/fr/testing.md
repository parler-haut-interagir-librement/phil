<!-- phil-dev source-sha256=b7545f8f6c2de8d75c5ce51dabbf34897bd541018c7e51b29f7b6d7361ae405b -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` écrit ses polices octet par octet : `FontBuilder`, dans `tests/Unit/Fixture`, construit un `sfnt`
à partir de ses tables, des collections, des fichiers WOFF compressés avec zlib et des fichiers WOFF2 relus avec un
décompresseur identité, ainsi que les tables `name`, `OS/2`, `head`, `post`, `fvar` et `cmap`. Elle ne dépend donc
d'aucun fichier de police. Elle couvre chaque conteneur et chaque façon dont un fichier peut être brisé, le choix des
noms et le décodage Mac Roman, les valeurs par défaut et les intervalles de chaque valeur, les bits d'incorporation un
par un et ensemble, les plages de caractères et leur fusion, le CSS écrit exactement, son échappement, et le registre au
moyen d'un répertoire temporaire et de son JSON.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/fonts/tests
```

Infection exige de la bibliothèque un score de mutation couvert de 100 %.

Les conteneurs ont aussi été vérifiés avec de vrais fichiers : des polices du système, collections comprises, et des
fichiers WOFF2 produits par le `woff2_compress` de Google et lus au moyen de l'outil en ligne de commande `brotli` comme
décompresseur.

## Tester du code qui lit des polices

Construisez directement une `Font` plutôt qu'un fichier lorsque le code ne lit que ses métadonnées ; chaque argument du
constructeur a une valeur par défaut :

```php
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\Font;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;

$font = new Font(
    names: new Names([new NameRecord(3, 1, 0x0409, 1, 'Example Sans')]),
    weight: 700,
    embedding: new EmbeddingPermissions(0x0002),
);
```

Les règles `@font-face` et le JSON du registre sont déterministes : comparez-les exactement. Un test qui a besoin d'un
fichier WOFF2 peut passer `static fn (string $data): string => $data` comme décompresseur et stocker ses tables sans
compression.
