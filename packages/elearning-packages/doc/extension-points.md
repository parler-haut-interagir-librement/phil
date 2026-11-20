# Extension points

The library is a set of immutable readers and values. What an application configures, replaces or relies on:

## `PackageLimits`

The bounds of every reading: see [Limits and security](limits-and-security.md). An application narrows them for an
upload form and widens them for a migration of large courses. A `PackageLimits` is a value: it is built once, and given
to `PackageReader`, to the readers and to `ZipPackage::open()`.

## The language of the texts

`PackageReader`, `Cmi5CourseReader` and `XapiCourseReader` take the language in which a cmi5 title or description, or
the title and the launch of an xAPI course, is chosen, such as `fr`; the first text written is chosen when the language
is absent or not given. SCORM titles have no language. `XapiActivity` keeps every language, for `name()` and
`description()` to choose from.

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

`Package`, `ScormManifest`, `ScormOrganization`, `ScormItem`, `ScormResource`, `Cmi5Course`, `Cmi5Block`, `Cmi5Au`,
`XapiCourse`, `XapiActivity`, the activity tree and its sequencing values are immutable, with public properties: an
application maps them to its own model. Their constructors are public, so that an application builds them in its
tests — an `ActivityTree` built by hand is sequenced as one read from a manifest.

## The sequencing state

`SequencingState` is the one thing a platform stores for sequencing: `toArray()` gives plain values for a JSON column
or a document, `fromArray()` reads them back against the tree. Where and when it is stored, and how global objectives
are shared across courses, is the platform's: see [SCORM sequencing](scorm-sequencing.md).

## Stability

The package is at `0.x`: its API may change between minor versions until its first stable release. `XmlDocument`, the
`SequencingBuilder`, the classes of one request of the sequencer and the parts of a prerequisites expression are
internal: the readers and the sequencer use them, an application does not.
