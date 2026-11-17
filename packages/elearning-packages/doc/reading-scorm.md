# Reading SCORM

A SCORM package is an IMS Content Packaging archive: `imsmanifest.xml` at its root describes organizations — trees of
items, each launching a resource — and the resources, each a launch file and the files it needs. SCORM 1.2 and SCORM
2004 share that shape; SCORM 2004 adds completion thresholds and sequencing.

## From a package

```php
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormManifest;

$package = new PackageReader()->readFile('/path/to/course.zip');

if ($package->manifest instanceof ScormManifest) {
    $organization = $package->manifest->organization(); // the default organization, else the first
}
```

`PackageReader` reads `cmi5.xml` when the package holds it, and `imsmanifest.xml` otherwise; a package holding neither
is refused. After the manifest, it checks what the manifest names against the files of the package, and notes in
`$package->warnings`:

| Note | When |
|---|---|
| `The launch file "…" of the item "…" is not in the package.` | An item, of any organization, launches a resource whose launch file is missing |
| `The item "…" names the resource "…", which the manifest does not describe.` | An item's `identifierref` names no resource |
| `The file "…" of the resource "…" is not in the package.` | A resource declares a `<file>` that is missing |

A launch file that is an absolute `http` or `https` address is not checked: the package does not hold it. The notes
are each said once, at most a hundred and a last one counting those left out.

## From a manifest alone

```php
use Phil\ElearningPackages\Scorm\ScormManifestReader;

$manifest = new ScormManifestReader()->read($xml);
```

The reader takes the text of `imsmanifest.xml` and checks nothing against files; it throws `PackageRefused` when the
manifest is unsafe, malformed, of an unknown version, or describes no course.

## The version

The version comes from `<schemaversion>` in the metadata of the manifest:

| `<schemaversion>` | `format` |
|---|---|
| `1.2` | `PackageFormat::Scorm12` |
| `CAM 1.3`, `1.3…`, any text holding `2004` | `PackageFormat::Scorm2004` |
| Missing or other, with the `adlcp_v1p3` namespace | `PackageFormat::Scorm2004` |
| Missing or other, with the `adlcp_rootv1p2` namespace | `PackageFormat::Scorm12` |
| Anything else | Refused |

`schemaVersion` keeps the text as written, or the format's value when the manifest has none.

## What is read

Elements are matched by their local names, so that a manifest with or without the usual namespaces reads the same.

| Value | From |
|---|---|
| `ScormManifest::$identifier` | The `identifier` of `<manifest>`, 1 to 255 bytes |
| `ScormManifest::$organizations` | Each `<organization>` that has items; a manifest with none is refused |
| `ScormManifest::$defaultOrganization` | The `default` of `<organizations>` |
| `ScormManifest::$resources` | Each `<resource>`, by identifier |
| `ScormManifest::$sequencingCollection` | `<sequencingCollection>` as XML, when 64 KiB or less |
| `ScormOrganization::$title`, `ScormItem::$title` | `<title>`, else the identifier |
| `ScormItem::$resource`, `$parameters`, `$visible` | `identifierref`, `parameters`, `isvisible` |
| `ScormItem::$children` | The nested `<item>` elements |
| `ScormItem::$masteryScore` | SCORM 1.2's `<masteryscore>` out of a hundred, as a scaled score from 0 to 1 |
| `ScormItem::$prerequisites` | SCORM 1.2's `<prerequisites>` |
| `ScormItem::$completionThreshold` | SCORM 2004's `<completionThreshold>`, its `minProgressMeasure` or its text, from 0 to 1 |
| `ScormOrganization::$sequencing`, `ScormItem::$sequencing` | `<sequencing>` as XML, when 64 KiB or less |
| `ScormResource::$scormType` | `adlcp:scormtype` or `adlcp:scormType`, lower case; `asset` by default |
| `ScormResource::$href`, `$files` | The launch file and the `<file>` elements, resolved against every `xml:base` |
| `ScormResource::$dependencies` | The `identifierref` of each `<dependency>` |

A value that is out of range — a mastery score above a hundred, a threshold above 1, text where a number is expected —
reads as null. A text is trimmed and kept to 4 096 characters.

## Launch files

A resource's `href` and each `<file>` are resolved as relative references: against the `xml:base` of `<resources>`,
then of the resource; their query and fragment set apart and kept; their escapes decoded; their `.` segments dropped.
The path is then checked by `PackagePath` — a reference that would leave the package, such as `../index.html` or
`%2e%2e/index.html`, refuses the manifest. An absolute `http` or `https` address is kept as written.

```php
$resource = $manifest->resources['res-saws'];
$resource->href;    // 'content/tools/saws.html?lang=en'
$resource->isSco(); // true
```

The `parameters` of an item are not joined to the launch file: IMS Content Packaging appends them, and the caller
decides how.

## What the library leaves to its caller

It does not run SCOs, implement the SCORM runtime API, enforce sequencing or prerequisites, nor turn the manifest into
a course: those belong to a platform. It reads what such a platform needs to decide.
