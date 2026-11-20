<!-- phil-dev source-sha256=248aa4225c651c3a6bbc5be9aeea904c30cf619dcf79ecc69b8e2f8a94925cf0 -->
# Points d'extension

La bibliothèque est un ensemble de lecteurs et de valeurs immuables. Ce qu'une application configure, remplace ou sur quoi
elle s'appuie :

## `PackageLimits`

Les bornes de chaque lecture : voir [Limites et sécurité](limits-and-security.md). Une application les resserre pour un
formulaire de téléversement et les élargit pour une migration de gros cours. Un `PackageLimits` est une valeur : on le
construit une fois, et on le donne à `PackageReader`, aux lecteurs et à `ZipPackage::open()`.

## La langue des textes

`PackageReader`, `Cmi5CourseReader` et `XapiCourseReader` prennent la langue dans laquelle on choisit un titre ou une
description cmi5, ou le titre et le lancement d'un cours xAPI, comme `fr` ; le premier texte écrit est choisi quand la
langue est absente ou n'est pas donnée. Les titres SCORM n'ont pas de langue. `XapiActivity` garde toutes les langues,
pour que `name()` et `description()` y choisissent.

## `PackageOpener` et `PackageArchive`

`Phil\ElearningPackages\Contract\PackageOpener` ouvre un paquet en lecture et renvoie un
`Phil\ElearningPackages\Contract\PackageArchive` : les noms de ses fichiers, `has()`, `read()` et `close()`.
`ZipPackageOpener` ouvre les archives ZIP par l'extension zip de PHP, et c'est la valeur par défaut de `PackageReader`.

Une application qui garde ses paquets sous une autre forme — un stockage d'objets, une archive déjà dépaquetée par un
outil d'analyse — écrit son propre ouvreur et le donne à `PackageReader` :

```php
$reader = new PackageReader($limits, 'fr', new StoredPackageOpener($storage));
```

Son archive doit garder les garanties de `ZipPackage` : chaque nom qu'elle liste vérifié par `PackagePath::check()`,
chaque lecture bornée par les limites avec lesquelles elle a été ouverte, et chaque lecture refusée après `close()`.
`PackageReader::read()` accepte aussi une archive que l'application a ouverte elle-même.

## Les valeurs lues

`Package`, `ScormManifest`, `ScormOrganization`, `ScormItem`, `ScormResource`, `Cmi5Course`, `Cmi5Block`, `Cmi5Au`,
`XapiCourse`, `XapiActivity`, l'arbre d'activités et ses valeurs de séquencement sont immuables, avec des propriétés
publiques : une application les transpose dans son propre modèle. Leurs constructeurs sont publics, pour qu'une
application les construise dans ses tests — un `ActivityTree` construit à la main est séquencé comme un arbre lu dans un
manifeste.

## L'état de séquencement

`SequencingState` est la seule chose qu'une plateforme stocke pour le séquencement : `toArray()` donne des valeurs
simples pour une colonne JSON ou un document, `fromArray()` les relit en les confrontant à l'arbre. Où et quand il est
stocké, et comment les objectifs globaux sont partagés entre des cours, c'est l'affaire de la plateforme : voir
[Séquencement SCORM](scorm-sequencing.md).

## Stabilité

Le paquet est en `0.x` : son API peut changer entre des versions mineures jusqu'à sa première version stable.
`XmlDocument`, le `SequencingBuilder`, les classes d'une seule demande du séquenceur et les parties d'une expression de
prérequis sont internes : les lecteurs et le séquenceur les utilisent, une application non.
