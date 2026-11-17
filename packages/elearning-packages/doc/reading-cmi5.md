# Reading cmi5

A cmi5 course is described by a course structure, `cmi5.xml`: the course, then blocks and assignable units in order,
each with an IRI as identifier. An assignable unit names its launch URL — a file of the package, or an absolute
address — and how it is satisfied.

## From a package

```php
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\PackageReader;

$package = new PackageReader(language: 'fr')->readFile('/path/to/course.zip');

if ($package->manifest instanceof Cmi5Course) {
    $package->manifest->children; // blocks and assignable units, in order
}
```

A package holding `cmi5.xml` at its root is read as cmi5, even when it also holds `imsmanifest.xml`. Each unit whose URL
is relative is resolved, checked to stay inside the package, and noted when its file is missing:
`The launch file "…" of the assignable unit "…" is not in the package.` A unit whose URL leaves the package refuses it.

## A course structure alone

A platform may receive `cmi5.xml` alone, its units served from elsewhere:

```php
$package = new PackageReader()->readStructure($xml);
```

`$package->files` is empty, and a unit that names a file rather than an absolute address is noted, since no package
holds it.

## From the structure text

```php
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;

$course = new Cmi5CourseReader(language: 'fr-CA')->read($xml);
```

## What is read

| Value | From |
|---|---|
| `Cmi5Course::$id`, `Cmi5Block::$id`, `Cmi5Au::$id` | The `id` attribute: 1 to 255 bytes without white space, unique in the structure |
| `$title`, `$description` | The `<langstring>` of `<title>` and `<description>` in the language asked for, else the first; the title defaults to the identifier |
| `Cmi5Course::$children`, `Cmi5Block::$children` | The `<block>` and `<au>` elements, in order |
| `Cmi5Course::$hasObjectives` | Whether the structure holds `<objectives>`, which the library does not read further |
| `Cmi5Au::$url` | `<url>`, 1 to 2 048 bytes |
| `Cmi5Au::$moveOn` | `moveOn`, a `MoveOn`; `NotApplicable` by default |
| `Cmi5Au::$masteryScore` | `masteryScore`, a decimal from 0 to 1 |
| `Cmi5Au::$launchMethod` | `launchMethod`, a `LaunchMethod`; `AnyWindow` by default |
| `Cmi5Au::$launchParameters`, `$entitlementKey` | `<launchParameters>`, `<entitlementKey>` |
| `Cmi5Au::$activityType` | `activityType` |

The language matches by prefix and without case: `fr` chooses `fr-CA`. A structure without any assignable unit, a
`moveOn` or `launchMethod` cmi5 does not define, or a mastery score outside 0 and 1 is refused.

## `MoveOn`

What satisfies a unit, as cmi5 defines it:

```php
use Phil\ElearningPackages\Cmi5\MoveOn;

MoveOn::CompletedAndPassed->isMetBy(completed: true, success: true);  // true
MoveOn::CompletedOrPassed->isMetBy(completed: false, success: null);  // false
MoveOn::NotApplicable->isMetBy(completed: false, success: null);      // true
```

## What the library leaves to its caller

It does not launch units, fetch tokens, nor receive or check xAPI statements: those belong to a platform, which builds
them on what the library reads.
