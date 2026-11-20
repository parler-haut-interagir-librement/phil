<!-- phil-dev source-sha256=480a3ec8896414d69066d8fedea1e4cc888050982cc42c7c168ee03ff93dd259 -->
# Exceptions

Chaque exception que la bibliothèque lève volontairement implémente
`Phil\ElearningPackages\Exception\ElearningPackagesException` : un appelant attrape cette interface pour toutes les
traiter.

| Exception | Étend | Levée quand |
|---|---|---|
| `PackageRefused` | `RuntimeException` | Un paquet, un chemin ou un manifeste ne peut pas être lu en toute sécurité : voir ci-dessous |
| `InvalidLimits` | `InvalidArgumentException` | Une borne donnée à `PackageLimits` n'est pas un nombre positif |
| `RuntimeRefused` | `InvalidArgumentException` | Des données de suivi données à `CmiData` contiennent une valeur qui n'est pas du texte ou un élément que la version ne définit pas ; `CmiData::put()` ne nomme aucun élément ; `LaunchSettings` contient un apprenant, un mode, une note, un seuil ou une action de limite de temps hors de ses bornes ; `ScormVersion::of()` reçoit cmi5 ou xAPI |
| `SequencingRefused` | `InvalidArgumentException` | `SequencingState::fromArray()` reçoit un état d'une autre version, qui nomme une activité que l'arbre ne contient pas ou qui contient une valeur hors de ses bornes ; `Sequencer::report()` est appelée sans qu'aucune tentative soit en cours, ou avec un `lesson_status` que SCORM 1.2 ne définit pas ; `ActivityTree::get()` ou `Sequencer::lessonStatus()` nomme une activité que l'arbre ne contient pas |

## `PackageRefused`

Son message dit pourquoi, en une phrase anglaise, bonne à montrer à la personne qui a envoyé le paquet :

| Levée par | Quand |
|---|---|
| `ZipPackage::open()`, `ZipPackageOpener::open()`, `PackageReader::readFile()` | Le fichier est absent, trop gros ou n'est pas une archive ZIP ; une entrée n'est pas sûre ou dépasse une limite |
| `ZipPackage::read()` | Le fichier est absent du paquet, illisible, corrompu, n'a pas la taille que son entrée annonçait, ou les octets lus dépassent `totalBytes` ; l'archive est fermée |
| `PackagePath::check()`, `PackagePath::resolve()` | Le chemin n'est pas de ceux qu'a un fichier d'un paquet |
| `ScormManifestReader::read()` | Le manifeste est trop gros, déclare un type de document ou une entité, est mal formé, n'a pas de racine `<manifest>`, ne nomme aucune version que le lecteur connaît, a un identifiant absent ou trop long, imbrique ou contient trop d'éléments, ou ne décrit aucune organisation qui ait des éléments |
| `Cmi5CourseReader::read()` | La structure est trop grosse, non sûre ou mal formée, n'a pas de racine `<courseStructure>` ni de `<course>`, a un identifiant absent, trop long, contenant une espace blanche ou utilisé deux fois, une unité sans URL, un `moveOn`, un `launchMethod` ou un `masteryScore` que cmi5 n'admet pas, imbrique ou contient trop de blocs et d'unités, ou ne contient aucune unité |
| `XapiCourseReader::read()` | La description est trop grosse, non sûre ou mal formée, n'a pas de racine `<tincan>` ni de `<activities>`, a une activité sans IRI, plus d'activités que `items`, aucune activité avec un lancement, ou un lancement qui sort du paquet, d'un autre schéma, ou une adresse absolue que `phil/url` refuse |
| `SequencingReader::read()` | L'un des refus de `ScormManifestReader::read()` ; le manifeste n'a aucune organisation de cet identifiant ; une activité déclare plus de règles, de conditions, d'objectifs ou de correspondances que les bornes le permettent |
| `Prerequisites::parse()` | L'expression est vide, plus longue que 4 096 octets, imbriquée sur plus de 32 niveaux, ou n'est pas de celles que la grammaire admet |
| `PackageReader::read()` | Le paquet ne contient aucun de `cmi5.xml`, `imsmanifest.xml` et `tincan.xml` à sa racine, ou l'un des cas ci-dessus |

Un refus est définitif pour ce paquet : le relire avec les mêmes limites le refuse de nouveau. Une demande de navigation que le séquenceur refuse n'est pas une
exception : c'est un `SequencingResult` d'issue `Refused`, avec sa raison.

```php
use Phil\ElearningPackages\Exception\ElearningPackagesException;
use Phil\ElearningPackages\Exception\PackageRefused;

try {
    $package = $reader->readFile($path);
} catch (PackageRefused $refused) {
    $form->addError($refused->getMessage());
}
```
