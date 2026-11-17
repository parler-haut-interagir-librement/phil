<!-- phil-dev source-sha256=bbf72a8635fe4ac03f4d314f3586b329bb6cdc0aa768d2354c1ed89a485a47b3 -->
# Points d'extension

La bibliothèque est un ensemble de lecteurs et de valeurs immuables. Ce qu'une application configure, remplace ou sur quoi
elle s'appuie :

## `PackageLimits`

Les bornes de chaque lecture : voir [Limites et sécurité](limits-and-security.md). Une application les resserre pour un
formulaire de téléversement et les élargit pour une migration de gros cours. Un `PackageLimits` est une valeur : on le
construit une fois, et on le donne à `PackageReader`, aux lecteurs et à `ZipPackage::open()`.

## La langue des textes

`PackageReader` et `Cmi5CourseReader` prennent la langue dans laquelle on choisit un titre ou une description cmi5, comme
`fr` ; le premier texte écrit est choisi quand la langue est absente ou n'est pas donnée. Les titres SCORM n'ont pas de
langue.

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

`Package`, `ScormManifest`, `ScormOrganization`, `ScormItem`, `ScormResource`, `Cmi5Course`, `Cmi5Block` et `Cmi5Au` sont
immuables, avec des propriétés publiques : une application les transpose dans son propre modèle. Leurs constructeurs sont
publics, pour qu'une application les construise dans ses tests.

## Stabilité

Le paquet est en `0.x` : son API peut changer entre des versions mineures jusqu'à sa première version stable.
`XmlDocument` est interne : les lecteurs l'utilisent, une application non.
