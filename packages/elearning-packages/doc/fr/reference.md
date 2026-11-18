<!-- phil-dev source-sha256=0395f976d470ccaa4f6da9af5488c9f8bdc55d92e56d0d8abc16f8d8617ddd37 -->
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
| `Scorm\ScormItem` | Un élément : identifiant, titre, ressource, paramètres, visibilité, enfants, score de maîtrise, prérequis, seuil d'achèvement, séquencement, données de lancement, temps alloué et son action, note de passage normalisée ; `isLeaf()` |
| `Scorm\ScormResource` | Une ressource : identifiant, type SCORM, fichier de lancement, fichiers, dépendances ; `isSco()` |

## L'environnement d'exécution SCORM

| Classe | Rôle |
|---|---|
| `Runtime\ScormVersion` | `Scorm12`, `Scorm2004` ; `of()` un format de paquet, `apiName()`, `dataModelVersion()`, `statusElement()`, `core()` |
| `Runtime\DataModel` | Les éléments d'une version, ses collections et leurs bornes, `children()`, `responsePattern()`, `describe()` pour le navigateur |
| `Runtime\ElementDefinition`, `Access`, `ValueCheck` | Un élément : qui le lit et l'écrit, son motif, sa longueur, son intervalle et sa valeur par défaut |
| `Runtime\ElementName` | Un nom lu selon son motif, ses index et son mot-clé |
| `Runtime\CmiData` | Les données de suivi d'une tentative : `get()`, `set()`, `put()`, `forget()`, `values()`, `count()`, `status()`, `scaledScore()` |
| `Runtime\CmiRules` | Les règles de SCORM 2004 qui lient un élément à d'autres : dépendances, identifiants uniques, statuts évalués |
| `Runtime\CmiCommit` | Applique une validation, et donne les éléments laissés de côté avec leurs codes d'erreur |
| `Runtime\ScormSession`, `SessionState` | L'API d'exécution d'une session et ses états |
| `Runtime\CmiAttempt`, `LaunchSettings`, `Entry`, `SessionEnd` | Ce que la plateforme fixe au début d'une session, et décide à sa fin |
| `Runtime\RuntimeStatus`, `Score`, `Duration` | Achèvement, réussite et note normalisée ; les temps de chaque version |
| `Runtime\RuntimeResult`, `ScormError`, `Mistake` | Ce qu'un appel répond ; les codes de chaque version et leurs messages |

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
| `Exception\RuntimeRefused` | Des données de suivi ou des paramètres de lancement inutilisables |

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
| `tests/Benchmark/` | Les bancs d'essai des lecteurs et des validations |
