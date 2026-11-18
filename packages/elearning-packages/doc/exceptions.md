# Exceptions

Every exception the library throws on purpose implements `Phil\ElearningPackages\Exception\ElearningPackagesException`:
a caller catches that interface to handle them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `PackageRefused` | `RuntimeException` | A package, a path or a manifest cannot be read safely: see below |
| `InvalidLimits` | `InvalidArgumentException` | A bound given to `PackageLimits` is not a positive number |
| `RuntimeRefused` | `InvalidArgumentException` | Tracking data given to `CmiData` holds a value that is not text or an element the version does not define; `CmiData::put()` names no element; `LaunchSettings` holds a learner, a mode, a score, a threshold or a time limit action out of its bounds; `ScormVersion::of()` is given cmi5 |

## `PackageRefused`

Its message says why, in one English sentence, fit to show to the person who sent the package:

| Thrown by | When |
|---|---|
| `ZipPackage::open()`, `ZipPackageOpener::open()`, `PackageReader::readFile()` | The file is missing, too large or not a ZIP archive; an entry is unsafe or past a limit |
| `ZipPackage::read()` | The file is missing from the package, cannot be read, is corrupt, is not the size its entry announced, or the bytes read pass `totalBytes`; the archive is closed |
| `PackagePath::check()`, `PackagePath::resolve()` | The path is not one a file of a package has |
| `ScormManifestReader::read()` | The manifest is too large, declares a document type or an entity, is not well formed, has no `<manifest>` root, names no version the reader knows, has an identifier missing or too long, nests or holds too many items, or describes no organization with items |
| `Cmi5CourseReader::read()` | The structure is too large, unsafe or malformed, has no `<courseStructure>` root or `<course>`, has an identifier missing, too long, holding white space or used twice, a unit without a URL, a `moveOn`, `launchMethod` or `masteryScore` cmi5 does not allow, nests or holds too many blocks and units, or holds no unit |
| `PackageReader::read()` | The package holds neither `cmi5.xml` nor `imsmanifest.xml` at its root, or one of the above |

A refusal is final for that package: reading it again with the same limits refuses it again.

```php
use Phil\ElearningPackages\Exception\ElearningPackagesException;
use Phil\ElearningPackages\Exception\PackageRefused;

try {
    $package = $reader->readFile($path);
} catch (PackageRefused $refused) {
    $form->addError($refused->getMessage());
}
```
