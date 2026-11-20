<!-- phil-dev source-sha256=fca2a3092c3d41f2703c85f21e04b6d2ea9fb65018e7e1e15f779a861d040b72 -->
# Référence

Chaque classe de la bibliothèque, par espace de noms sous `Phil\ElearningPackages`.

## Lire un paquet

| Classe | Rôle |
|---|---|
| `PackageReader` | Lit un paquet quel que soit son format : `readFile(string $path)`, `read(PackageArchive $archive)`, `readStructure(string $xml)` ; renvoie un `Package` |
| `Package` | Le format, le manifeste (`ScormManifest`, `Cmi5Course` ou `XapiCourse`), les fichiers et les avertissements d'une lecture ; `identifier()`, `title()` |
| `PackageFormat` | `Scorm12`, `Scorm2004`, `Cmi5`, `Xapi` ; `manifest()` nomme le fichier qui décrit le format |
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

## xAPI

| Classe | Rôle |
|---|---|
| `Xapi\XapiCourseReader` | Lit `tincan.xml` dans la langue demandée ; `FILE` le nomme, `MAX_LANGUAGES` borne les textes d'une activité |
| `Xapi\XapiCourse` | L'activité du cours, son titre, chaque activité et les avertissements ; `id()`, `activity()`, `launchable()`, `interactions()` |
| `Xapi\XapiActivity` | Une activité : IRI, type, noms et descriptions par langue, lancement, type d'interaction ; `name()`, `description()`, `isLaunchable()` ; `COURSE` et `INTERACTION` nomment les deux types qu'elle connaît |
| `Xapi\InteractionType` | Les types d'interaction de xAPI |
| `Xapi\XapiWarning`, `XapiWarningKind` | Un problème laissé de côté : son genre, son activité et sa phrase |

## Séquencement

| Classe | Rôle |
|---|---|
| `Sequencing\SequencingReader` | Lit l'arbre d'activités d'une organisation de `imsmanifest.xml` ; ses constantes bornent les règles, conditions, objectifs et correspondances d'une activité |
| `Sequencing\ActivityTree` | La racine, le format et les avertissements ; `get()`, `has()`, `parent()`, `path()`, `activities()`, `isScorm12()` |
| `Sequencing\Activity` | Une activité : identifiant, titre, séquencement, enfants, visibilité, ressource, prérequis ; `isLeaf()` |
| `Sequencing\Sequencing` | Ses modes de contrôle, ses règles par moment, ses limites, ses règles et contrôles de cumul, ses objectifs, ses contrôles de randomisation et de livraison ; `primaryObjective()`, `objective()`, `rules()` |
| `Sequencing\ControlMode`, `LimitConditions`, `DeliveryControls`, `RandomizationControls`, `RandomizationTiming` | Les éléments de `<imsss:sequencing>` du même nom |
| `Sequencing\SequencingRule`, `RuleCondition`, `ConditionType`, `RuleAction`, `RuleTiming` | Une règle de séquencement, ses conditions et son action |
| `Sequencing\RollupRule`, `RollupAction`, `ChildActivitySet`, `RollupControls`, `Requirement` | Une règle de cumul, et comment un enfant compte dans le cumul de son parent |
| `Sequencing\Objective`, `ObjectiveMap` | Un objectif et ses correspondances avec des objectifs globaux ; `Objective::PRIMARY` désigne un objectif primaire sans identifiant |
| `Sequencing\Prerequisite\Prerequisites` | Les prérequis d'un élément de SCORM 1.2 : `parse()`, `isMet()`, `identifiers()` ; `STATUSES` liste les statuts de SCORM 1.2 |
| `Sequencing\Sequencer` | `navigate()`, `report()`, `lessonStatus()` |
| `Sequencing\NavigationRequest` | `Start`, `ResumeAll`, `Continue`, `Previous`, `Choice`, `Exit`, `ExitAll`, `SuspendAll` |
| `Sequencing\SequencingResult`, `Outcome` | Ce qu'une demande a donné : l'issue, l'état, l'activité livrée, la raison d'un refus |
| `Sequencing\SequencingState` | L'état qu'une plateforme conserve : `begin()`, `toArray()`, `fromArray()`, `activity()`, `global()`, `globals()` |
| `Sequencing\ActivityStatus`, `ObjectiveStatus` | Ce qu'un état sait d'une activité et d'un objectif |
| `Sequencing\AttemptResult` | Ce que le contenu de l'activité livrée a rapporté : `fromRuntime()`, `fromLessonStatus()` |

## Exceptions

| Classe | Rôle |
|---|---|
| `Exception\ElearningPackagesException` | L'interface de chaque exception que la bibliothèque lève volontairement |
| `Exception\PackageRefused` | Un paquet, un chemin ou un manifeste qui ne peut pas être lu en toute sécurité |
| `Exception\InvalidLimits` | Une borne de `PackageLimits` qui n'est pas positive |
| `Exception\RuntimeRefused` | Des données de suivi ou des paramètres de lancement inutilisables |
| `Exception\SequencingRefused` | Un état de séquencement qui ne correspond pas à son arbre, ou des résultats rapportés sans tentative en cours |

## Interne

| Classe | Rôle |
|---|---|
| `Xml\XmlDocument` | La lecture XML sûre que partagent les lecteurs : ne fait pas partie de l'API publique |
| `Sequencing\SequencingBuilder` | Construit les activités d'une organisation pour `SequencingReader` |
| `Sequencing\SequencingRun`, `SequencingData`, `SequencingRules`, `SequencingFlow`, `SequencingRollup`, `SequencingAttempts` | Une demande du séquenceur sur une copie de travail de l'état |
| `Sequencing\Prerequisite\Node`, `ItemStatus`, `Negation`, `AtLeast` | Les parties d'une expression de prérequis |

## Fichiers

| Fichier | Rôle |
|---|---|
| `phil-dev.php` | Les chemins, les cibles de fuzzing et les mots de la documentation, pour l'outillage du monodépôt |
| `composer-dependency-analyser.php` | Les dépendances déclarées, vérifiées contre le paquet installé seul |
| `tests/Fuzz/` | Les cibles de fuzzing et leur corpus |
| `tests/Benchmark/` | Les bancs d'essai des lecteurs, des validations et du séquenceur |
