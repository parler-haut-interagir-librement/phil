<!-- phil-dev source-sha256=49efa3c1a6f0da463cfdd0522a33fb691973172275e0d158cce8030a9679339e -->
# Référence

Chaque classe de la bibliothèque, par espace de noms sous `Phil\ElearningPackages`.

## Lire un paquet

| Classe | Rôle |
|---|---|
| `PackageReader` | Lit un paquet quel que soit son format : `readFile(string $path)`, `read(PackageArchive $archive)`, `readStructure(string $xml)` ; renvoie un `Package` |
| `Package` | Le format, le manifeste (`ScormManifest` ou `Cmi5Course`), les fichiers et les avertissements d'une lecture ; `identifier()`, `title()` |
| `PackageFormat` | `Scorm12`, `Scorm2004`, `Cmi5` ; `manifest()` nomme le fichier qui décrit le format |
| `PackageLimits` | Les bornes d'une lecture ; ses constantes contiennent les valeurs par défaut |
| `PackagePath` | `check()` un chemin dans un paquet, `resolve()` la référence d'un manifeste selon ses bases, `isWebAddress()` |
| `Warnings` | Les notes d'une lecture, chacune dite une fois, au plus `Warnings::MAX` et une dernière qui compte celles laissées de côté |

## L'archive

| Classe | Rôle |
|---|---|
| `Contract\PackageOpener` | Ouvre un paquet en lecture : `open(string $path, PackageLimits $limits): PackageArchive` |
| `Contract\PackageArchive` | Un paquet ouvert : `files()`, `has()`, `read()`, `close()` |
| `Archive\ZipPackageOpener` | L'ouvreur des archives ZIP |
| `Archive\ZipPackage` | Une archive ZIP vérifiée avant qu'un fichier soit lu, ses fichiers lus dans les limites ; `ZipPackage::open()` |

## SCORM

| Classe | Rôle |
|---|---|
| `Scorm\ScormManifestReader` | Lit `imsmanifest.xml` de SCORM 1.2 ou de SCORM 2004 ; `FILE` le nomme |
| `Scorm\ScormManifest` | L'identifiant, le format, la version du schéma, les organisations, les ressources par identifiant, l'organisation par défaut et la collection de séquencement ; `organization()` |
| `Scorm\ScormOrganization` | Une organisation : identifiant, titre, éléments, séquencement |
| `Scorm\ScormItem` | Un élément : identifiant, titre, ressource, paramètres, visibilité, enfants, score de maîtrise, prérequis, seuil d'achèvement, séquencement ; `isLeaf()` |
| `Scorm\ScormResource` | Une ressource : identifiant, type SCORM, fichier de lancement, fichiers, dépendances ; `isSco()` |

## cmi5

| Classe | Rôle |
|---|---|
| `Cmi5\Cmi5CourseReader` | Lit `cmi5.xml` dans la langue demandée ; `FILE` le nomme |
| `Cmi5\Cmi5Course` | Le cours : IRI, titre, blocs et unités, description, s'il contient des objectifs |
| `Cmi5\Cmi5Block` | Un bloc : IRI, titre, blocs et unités, description |
| `Cmi5\Cmi5Au` | Une unité assignable : IRI, titre, URL, `moveOn`, score de maîtrise, mode de lancement, description, paramètres de lancement, clé de droit d'accès, type d'activité |
| `Cmi5\MoveOn` | `Completed`, `Passed`, `CompletedAndPassed`, `CompletedOrPassed`, `NotApplicable` ; `isMetBy()` |
| `Cmi5\LaunchMethod` | `AnyWindow`, `OwnWindow` |

## Exceptions

| Classe | Rôle |
|---|---|
| `Exception\ElearningPackagesException` | L'interface de chaque exception que la bibliothèque lève volontairement |
| `Exception\PackageRefused` | Un paquet, un chemin ou un manifeste qui ne peut pas être lu en toute sécurité |
| `Exception\InvalidLimits` | Une borne de `PackageLimits` qui n'est pas positive |

## Interne

| Classe | Rôle |
|---|---|
| `Xml\XmlDocument` | La lecture XML sûre que partagent les lecteurs : ne fait pas partie de l'API publique |

## Fichiers

| Fichier | Rôle |
|---|---|
| `phil-dev.php` | Les chemins, les cibles de fuzzing et les mots de la documentation, pour l'outillage du monodépôt |
| `composer-dependency-analyser.php` | Les dépendances déclarées, vérifiées contre le paquet installé seul |
| `tests/Fuzz/` | Les cibles de fuzzing et leur corpus |
| `tests/Benchmark/` | Les bancs d'essai des lecteurs |
