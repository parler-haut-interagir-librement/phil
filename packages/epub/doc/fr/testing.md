<!-- phil-dev source-sha256=846d671c99693344fe805395ed25d15c7a36d8a2df10c5034f8a50ac5b47d354 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` écrit des livres en mémoire et relit l'archive : l'ordre et la compression des entrées, leurs
dates et des octets reproductibles, le document de paquet, le document de navigation et les documents de contenu
exacts pour de petits livres, chaque métadonnée, les métadonnées d'accessibilité et ce qu'ajoute le rédacteur, les
liens et les ressources dans tous les cas, la table des matières, les repères et la liste de pages, chaque validation
et son message, la conversion XHTML de chaque construction, l'obscurcissement des polices contre les octets de la clé,
le découpage des fragments et des documents UJO.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/epub/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## EPUBCheck

EPUBCheck, le validateur du W3C, vérifie quatre livres : l'un avec chaque fonctionnalité de texte de la bibliothèque, l'un avec de l'audio, de la
vidéo, une piste de texte, une solution de repli, une superposition de médias et la table des matières EPUB 2, l'un en
mise en page fixe, et l'un fait à partir d'un document UJO. Il prend une vingtaine de secondes par livre ; le test ne s'exécute donc que quand
`EPUBCHECK` nomme l'exécutable :

```sh
brew install epubcheck          # or download it from the W3C and run it with Java
EPUBCHECK=$(command -v epubcheck) vendor/bin/phpunit packages/epub/tests --group epubcheck
```

Le test lance EPUBCheck avec `--failonwarnings` : un avertissement le fait échouer. Pour vérifier un livre d'une
application :

```sh
epubcheck book.epub
```

## Tester du code qui écrit des livres

Écrivez le livre en mémoire et ouvrez l'archive avec `ZipArchive`, ou comparez des fichiers du paquet :

```php
use Phil\Epub\EpubWriter;

$bytes = new EpubWriter()->toString($book);
$path = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($path, $bytes);
$zip = new ZipArchive();
$zip->open($path);

self::assertStringContainsString('<dc:title>The Lighthouse Keeper</dc:title>', $zip->getFromName('EPUB/package.opf'));
```

La sortie est déterministe : le même livre, avec la même date de modification, donne les mêmes octets.
