# phil/elearning-packages

**SCORM 1.2, SCORM 2004, cmi5 and xAPI packages read safely, with the SCORM run-time data model and sequencing.**

phil/elearning-packages reads the course packages that e-learning tools export: a ZIP archive holding `imsmanifest.xml`
(SCORM 1.2 or SCORM 2004, after IMS Content Packaging), `cmi5.xml` (a cmi5 course structure) or `tincan.xml` (an xAPI
package) at its root. It tells the format, reads the manifest into immutable values — organizations, items and
resources, blocks and assignable units, or xAPI activities — and notes what the manifest names but the package does not
hold, such as a missing launch file.

A package comes from elsewhere, so the library reads it as hostile input. The archive is checked as a whole before any
file is read: its size and number of entries, each entry's path, which must stay inside the package, symbolic links,
encrypted entries, the size each entry announces and its compression ratio. Each file is then read through a stream
that stops one byte past what its entry announced, and checked against its CRC-32. The manifest is parsed without any
document type or entity, without network access, and within bounds on its size, its XML nodes and their depth, counted
before it is loaded, and the depth and the number of items it describes. Nothing is extracted to the disk and nothing
is run.

It also holds the run-time data model of SCORM 1.2 and SCORM 2004 4th edition: every element, its type, vocabulary and
range, who may read and write it, the error codes of each version, the run-time API with its states, the rules of a
commit, the statuses and scores, and the session time added to the total time. A platform that runs SCORM content
checks the content's calls with it, in the browser through the description it gives and again on the server.

And it runs the sequencing of SCORM 2004 — control modes, pre-condition, exit and post-condition rules, limits, the
rollup of satisfaction, measure and completion, shared global objectives, selection and reordering of children — and
the prerequisites of SCORM 1.2, as a deterministic state machine: a navigation request and the state a platform kept
give the activity to launch and a new state, ready to store as JSON.

It serves a platform that imports and runs courses, an upload form that refuses a broken package before it is stored,
or a migration tool that lists what a set of packages holds. It is a plain PHP library: beyond the `dom`, `libxml`,
`mbstring`, `xmlreader` and `zip` extensions, it depends on `phil/url` alone, which reads an absolute launch address.

## Status

| Part | State |
|---|---|
| The ZIP archive checked before any file is read, then read within its limits | Implemented, tested and fuzzed |
| Paths inside a package, resolved against `xml:base` and checked | Implemented and tested |
| The manifest read without document type or entity, bounded in size, XML nodes, depth and items | Implemented, tested and fuzzed |
| SCORM 1.2 and SCORM 2004 manifests: version, organizations, items, resources, sequencing kept as XML | Implemented, tested and fuzzed |
| cmi5 course structures: course, blocks, assignable units, in the language asked for | Implemented, tested and fuzzed |
| xAPI packages (`tincan.xml`): activities, names and descriptions by language, launch, interaction types, typed warnings | Implemented, tested and fuzzed |
| The format told, the launch files and declared files checked | Implemented and tested |
| What a run time hands a SCO, read from the manifest: launch data, mastery score, scaled passing score, completion threshold, time allowed and its action | Implemented and tested |
| The SCORM run-time data model: the elements of SCORM 1.2 and 2004, their read and write rules and error codes, commits, statuses, scores, times, the API and its states | Implemented, tested and fuzzed |
| The SCORM 2004 sequencing read into an activity tree, and run: navigation requests, sequencing rules, limits, rollup, global objectives, selection and reordering, a state to store | Implemented, tested and fuzzed |
| The prerequisites of SCORM 1.2, parsed and applied by the same sequencer | Implemented, tested and fuzzed |
| Abandon and jump requests, constrained choice, completion by measure, available time ranges | Not supported: see [SCORM sequencing](doc/scorm-sequencing.md) |
| The run time itself — a page, an endpoint, the attempts kept —, an xAPI client, the import into a course model | Not in this library: a platform builds them on it |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reading SCORM](doc/reading-scorm.md) | The SCORM manifest, its version, organizations, items and resources. |
| [Reading cmi5](doc/reading-cmi5.md) | The cmi5 course structure, its blocks and assignable units, a structure alone. |
| [Reading xAPI packages](doc/reading-xapi.md) | `tincan.xml`, its activities, the course activity, the warnings and refusals. |
| [The SCORM run-time data model](doc/scorm-runtime.md) | The elements, reading and writing, the error codes, the API, a session's beginning and end, what is left to the platform. |
| [SCORM sequencing](doc/scorm-sequencing.md) | The activity tree, the navigation requests, the rules, the rollup, the prerequisites of SCORM 1.2, the state the platform keeps, what is not supported. |
| [Limits and security](doc/limits-and-security.md) | The limits, what the archive and the manifest are checked for, what the library never does. |
| [Exceptions](doc/exceptions.md) | What the library throws, and when. |
| [Extension points](doc/extension-points.md) | What an application configures, replaces or relies on. |
| [Testing](doc/testing.md) | The tests, the fuzz targets and the benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/elearning-packages
```

```php
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageReader;

try {
    $package = new PackageReader()->readFile($uploadedFile);
} catch (PackageRefused $refused) {
    echo $refused->getMessage(); // 'The path "../../etc/passwd" holds an empty, "." or ".." segment.'
}

$package->format;       // PackageFormat::Scorm2004
$package->title();      // 'First aid'
$package->identifier(); // 'org.example.first-aid'
$package->files;        // ['imsmanifest.xml', 'sco/start.html', …]
$package->warnings;     // ['The launch file "sco/quiz.html" of the item "a2" is not in the package.']
```

## Requirements

PHP 8.5 or later with the `dom`, `libxml`, `mbstring`, `xmlreader` and `zip` extensions, and those `phil/url` needs:
`intl` and `uri`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
