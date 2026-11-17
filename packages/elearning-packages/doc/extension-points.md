# Extension points

The library is a set of immutable readers and values. What an application configures, replaces or relies on:

## `PackageLimits`

The bounds of every reading: see [Limits and security](limits-and-security.md). An application narrows them for an
upload form and widens them for a migration of large courses. A `PackageLimits` is a value: it is built once, and given
to `PackageReader`, to the readers and to `ZipPackage::open()`.

## The language of the texts

`PackageReader` and `Cmi5CourseReader` take the language in which a cmi5 title or description is chosen, such as `fr`;
the first text written is chosen when the language is absent or not given. SCORM titles have no language.

## `PackageOpener` and `PackageArchive`

`Phil\ElearningPackages\Contract\PackageOpener` opens a package for reading, and returns a
`Phil\ElearningPackages\Contract\PackageArchive`: the names of its files, `has()`, `read()` and `close()`.
`ZipPackageOpener` opens ZIP archives through PHP's zip extension, and is the default of `PackageReader`.

An application that keeps packages in another form — an object storage, an archive already unpacked by a scanner —
writes its own opener and gives it to `PackageReader`:

```php
$reader = new PackageReader($limits, 'fr', new StoredPackageOpener($storage));
```

Its archive must keep the guarantees of `ZipPackage`: every name it lists checked by `PackagePath::check()`, every
read bounded by the limits it was opened with, and every read refused after `close()`. `PackageReader::read()` also
takes an archive the application opened itself.

## The values read

`Package`, `ScormManifest`, `ScormOrganization`, `ScormItem`, `ScormResource`, `Cmi5Course`, `Cmi5Block` and `Cmi5Au`
are immutable, with public properties: an application maps them to its own model. Their constructors are public, so
that an application builds them in its tests.

## Stability

The package is at `0.x`: its API may change between minor versions until its first stable release. `XmlDocument` is
internal: the readers use it, an application does not.
