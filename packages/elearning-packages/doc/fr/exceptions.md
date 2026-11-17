<!-- phil-dev source-sha256=d4fb268cf4319a0963c958e35246f0d56bba25f751e480b3463ba64216aba9c8 -->
# Exceptions

Chaque exception que la bibliothèque lève volontairement implémente
`Phil\ElearningPackages\Exception\ElearningPackagesException` : un appelant attrape cette interface pour toutes les
traiter.

| Exception | Étend | Levée quand |
|---|---|---|
| `PackageRefused` | `RuntimeException` | Un paquet, un chemin ou un manifeste ne peut pas être lu en toute sécurité : voir ci-dessous |
| `InvalidLimits` | `InvalidArgumentException` | Une borne donnée à `PackageLimits` n'est pas un nombre positif |

## `PackageRefused`

Son message dit pourquoi, en une phrase anglaise, bonne à montrer à la personne qui a envoyé le paquet :

| Levée par | Quand |
|---|---|
| `ZipPackage::open()`, `ZipPackageOpener::open()`, `PackageReader::readFile()` | Le fichier est absent, trop gros ou n'est pas une archive ZIP ; une entrée n'est pas sûre ou dépasse une limite |
| `ZipPackage::read()` | Le fichier est absent du paquet, illisible, corrompu, n'a pas la taille que son entrée annonçait, ou les octets lus dépassent `totalBytes` ; l'archive est fermée |
| `PackagePath::check()`, `PackagePath::resolve()` | Le chemin n'est pas de ceux qu'a un fichier d'un paquet |
| `ScormManifestReader::read()` | Le manifeste est trop gros, déclare un type de document ou une entité, est mal formé, n'a pas de racine `<manifest>`, ne nomme aucune version que le lecteur connaît, a un identifiant absent ou trop long, imbrique ou contient trop d'éléments, ou ne décrit aucune organisation qui ait des éléments |
| `Cmi5CourseReader::read()` | La structure est trop grosse, non sûre ou mal formée, n'a pas de racine `<courseStructure>` ni de `<course>`, a un identifiant absent, trop long, contenant une espace blanche ou utilisé deux fois, une unité sans URL, un `moveOn`, un `launchMethod` ou un `masteryScore` que cmi5 n'admet pas, imbrique ou contient trop de blocs et d'unités, ou ne contient aucune unité |
| `PackageReader::read()` | Le paquet ne contient ni `cmi5.xml` ni `imsmanifest.xml` à sa racine, ou l'un des cas ci-dessus |

Un refus est définitif pour ce paquet : le relire avec les mêmes limites le refuse de nouveau.

```php
use Phil\ElearningPackages\Exception\ElearningPackagesException;
use Phil\ElearningPackages\Exception\PackageRefused;

try {
    $package = $reader->readFile($path);
} catch (PackageRefused $refused) {
    $form->addError($refused->getMessage());
}
```
