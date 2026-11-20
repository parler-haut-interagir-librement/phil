<!-- phil-dev source-sha256=724027a5aafcc9aa4abb5391c0fbecb31421c008777f8d8a4c1ab1ecff251990 -->
# Lire SCORM

Un paquet SCORM est une archive IMS Content Packaging : `imsmanifest.xml`, à sa racine, décrit des organisations — des
arbres d'éléments, chacun lançant une ressource — et les ressources, chacune un fichier de lancement et les fichiers dont
elle a besoin. SCORM 1.2 et SCORM 2004 partagent cette forme ; SCORM 2004 ajoute des seuils d'achèvement et du
séquencement.

## À partir d'un paquet

```php
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormManifest;

$package = new PackageReader()->readFile('/path/to/course.zip');

if ($package->manifest instanceof ScormManifest) {
    $organization = $package->manifest->organization(); // the default organization, else the first
}
```

`PackageReader` lit `cmi5.xml` quand le paquet le contient, et `imsmanifest.xml` sinon ; un paquet qui ne contient ni
l'un ni l'autre est refusé. Après le manifeste, elle vérifie ce que le manifeste nomme contre les fichiers du paquet, et
note dans `$package->warnings` :

| Note | Quand |
|---|---|
| `The launch file "…" of the item "…" is not in the package.` | Un élément, de n'importe quelle organisation, lance une ressource dont le fichier de lancement est absent |
| `The item "…" names the resource "…", which the manifest does not describe.` | L'`identifierref` d'un élément ne nomme aucune ressource |
| `The file "…" of the resource "…" is not in the package.` | Une ressource déclare un `<file>` absent |

Un fichier de lancement qui est une adresse absolue `http` ou `https` n'est pas vérifié : le paquet ne le contient pas.
Les notes sont dites chacune une fois, au plus cent et une dernière qui compte celles laissées de côté.

## À partir d'un manifeste seul

```php
use Phil\ElearningPackages\Scorm\ScormManifestReader;

$manifest = new ScormManifestReader()->read($xml);
```

Le lecteur prend le texte de `imsmanifest.xml` et ne vérifie rien contre des fichiers ; il lève `PackageRefused` quand le
manifeste n'est pas sûr, est mal formé, est d'une version inconnue, ou ne décrit aucun cours.

## La version

La version vient de `<schemaversion>` dans les métadonnées du manifeste :

| `<schemaversion>` | `format` |
|---|---|
| `1.2` | `PackageFormat::Scorm12` |
| `CAM 1.3`, `1.3…`, tout texte contenant `2004` | `PackageFormat::Scorm2004` |
| Absent ou autre, avec l'espace de noms `adlcp_v1p3` | `PackageFormat::Scorm2004` |
| Absent ou autre, avec l'espace de noms `adlcp_rootv1p2` | `PackageFormat::Scorm12` |
| Tout autre cas | Refusé |

`schemaVersion` garde le texte tel qu'écrit, ou la valeur du format quand le manifeste n'en a pas.

## Ce qui est lu

Les éléments sont repérés par leur nom local, de sorte qu'un manifeste avec ou sans les espaces de noms usuels se lit de
la même façon.

| Valeur | Source |
|---|---|
| `ScormManifest::$identifier` | L'`identifier` de `<manifest>`, de 1 à 255 octets |
| `ScormManifest::$organizations` | Chaque `<organization>` qui a des éléments ; un manifeste qui n'en a aucune est refusé |
| `ScormManifest::$defaultOrganization` | Le `default` de `<organizations>` |
| `ScormManifest::$resources` | Chaque `<resource>`, par identifiant |
| `ScormManifest::$sequencingCollection` | `<sequencingCollection>` en XML, quand il fait 64 Kio ou moins |
| `ScormOrganization::$title`, `ScormItem::$title` | `<title>`, sinon l'identifiant |
| `ScormItem::$resource`, `$parameters`, `$visible` | `identifierref`, `parameters`, `isvisible` |
| `ScormItem::$children` | Les éléments `<item>` imbriqués |
| `ScormItem::$masteryScore` | Le `<masteryscore>` de SCORM 1.2 sur cent, en score normalisé de 0 à 1 |
| `ScormItem::$prerequisites` | Les `<prerequisites>` de SCORM 1.2 |
| `ScormItem::$completionThreshold` | Le `<completionThreshold>` de SCORM 2004, son `minProgressMeasure` ou son texte, de 0 à 1 |
| `ScormItem::$dataFromLms` | `<adlcp:datafromlms>` de SCORM 1.2 ou `<adlcp:dataFromLMS>` de SCORM 2004, 4 096 caractères au plus |
| `ScormItem::$maxTimeAllowed` | `<adlcp:maxtimeallowed>` de SCORM 1.2, `HHHH:MM:SS`, ou le `attemptAbsoluteDurationLimit` des conditions limites de SCORM 2004, une durée de l'ISO 8601 ; null quand ce n'est pas une durée |
| `ScormItem::$timeLimitAction` | `<adlcp:timelimitaction>`, l'un de `exit,message`, `exit,no message`, `continue,message`, `continue,no message` |
| `ScormItem::$scaledPassingScore` | Le `minNormalizedMeasure` d'un objectif principal satisfait par une mesure de SCORM 2004, 1 en son absence, de -1 à 1 |
| `ScormOrganization::$sequencing`, `ScormItem::$sequencing` | `<sequencing>` en XML, quand il fait 64 Kio ou moins |
| `ScormResource::$scormType` | `adlcp:scormtype` ou `adlcp:scormType`, en minuscules ; `asset` par défaut |
| `ScormResource::$href`, `$files` | Le fichier de lancement et les éléments `<file>`, résolus selon chaque `xml:base` |
| `ScormResource::$dependencies` | L'`identifierref` de chaque `<dependency>` |

Une valeur hors limites — un score de maîtrise au-dessus de cent, un seuil au-dessus de 1, du texte là où l'on attend un
nombre — se lit comme null. Un texte est rogné de ses espaces et gardé à 4 096 caractères.

## Les fichiers de lancement

Le `href` d'une ressource et chaque `<file>` sont résolus comme des références relatives : selon le `xml:base` de
`<resources>`, puis celui de la ressource ; leur requête et leur fragment mis à part et conservés ; leurs échappements
décodés ; leurs segments `.` retirés. Le chemin est ensuite vérifié par `PackagePath` — une référence qui sortirait du
paquet, comme `../index.html` ou `%2e%2e/index.html`, fait refuser le manifeste. Une adresse absolue `http` ou `https`
est conservée telle qu'écrite.

```php
$resource = $manifest->resources['res-saws'];
$resource->href;    // 'content/tools/saws.html?lang=en'
$resource->isSco(); // true
```

Les `parameters` d'un élément ne sont pas joints au fichier de lancement : IMS Content Packaging les ajoute, et l'appelant
décide comment.

## Ce que la bibliothèque laisse à son appelant

Elle n'exécute pas les SCO et ne transforme pas le manifeste en cours : cela revient à une plateforme. Elle lit ce dont
une telle plateforme a besoin pour décider. Le séquencement et les prérequis conservés ici tels qu'ils sont écrits sont
lus et appliqués par `SequencingReader` et `Sequencer` : voir [Séquencement SCORM](scorm-sequencing.md).
