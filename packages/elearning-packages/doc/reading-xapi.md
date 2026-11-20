# Reading xAPI packages

An xAPI package that is neither SCORM nor cmi5 describes its activities in `tincan.xml`, at the root of the archive:
each activity has an IRI, a type, a name and a description in one or more languages, and the activity a platform
launches names its launch file or address. Statements about these activities go to a Learning Record Store; the
library reads the package, and leaves the launch and the statements to the platform.

## From a package

```php
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Xapi\XapiCourse;

$package = new PackageReader(language: 'fr')->readFile('/path/to/course.zip');

if ($package->manifest instanceof XapiCourse) {
    $package->format;               // PackageFormat::Xapi
    $package->identifier();         // 'https://example.org/xapi/safety', the IRI of the course activity
    $package->title();              // its name in the language asked for
    $package->manifest->activities; // every activity, in order
}
```

A package is read as xAPI when `tincan.xml` is at its root and neither `cmi5.xml` nor `imsmanifest.xml` is: a package
that holds both a SCORM manifest and `tincan.xml` is read as SCORM. The launch file of each launchable activity is
checked: `The launch file "…" of the activity "…" is not in the package.` The issues the reader set aside are noted in
`$package->warnings` as sentences, and kept typed in `XapiCourse::$warnings`.

## From the description alone

```php
use Phil\ElearningPackages\Xapi\XapiCourseReader;

$course = new XapiCourseReader(language: 'fr-CA')->read($xml);

$course->id();           // the IRI of the course activity
$course->title;          // its name in the language asked for
$course->course;         // the course activity
$course->launchable();   // the activities a platform may launch
$course->interactions(); // the questions, of type cmi.interaction
$course->activity('https://example.org/xapi/quiz-1/q1');
```

The course activity is the first launchable activity of type `http://adlnet.gov/expapi/activities/course`, else the
first launchable activity.

## What is read

| Value | From |
|---|---|
| `XapiActivity::$id` | The `id` attribute: an IRI — a scheme, a colon, no white space —, 1 to 2 048 bytes |
| `XapiActivity::$type` | The `type` attribute, when it is an IRI |
| `XapiActivity::$names`, `$descriptions` | Each `<name>` and `<description>` by its `lang` attribute in lower case — `und` when absent or not a language tag —, the first of each language kept, empty texts left out, 32 languages at most |
| `XapiActivity::$launch` | The `<launch>` in the language asked for, else the first: a file of the package, resolved and checked, its query and fragment kept; or an absolute `http` or `https` address, in its canonical form |
| `XapiActivity::$interactionType` | `<interactionType>`, an `InteractionType`, without case |

`name()` and `description()` choose a text in a language: the tag itself, else the first text of its primary language
— `fr` and `fr-FR` choose `fr-CA` —, else the first written. A name defaults to the IRI.

## Warnings and refusals

What can be set aside is set aside, as an `XapiWarning` with its `XapiWarningKind`, the IRI of its activity and a
sentence:

| Kind | When |
|---|---|
| `UnnamedActivity` | An activity has no name: its IRI stands for it |
| `DuplicateActivity` | A second activity uses an IRI: it is left out |
| `InvalidActivityType` | A type is not an IRI: it is left out |
| `UnknownInteractionType` | An interaction type is not one xAPI defines: it is left out |
| `InteractionTypeWithoutInteraction` | An activity names an interaction type without being of type `cmi.interaction`: the type is kept |
| `TooManyLanguages` | A name or a description is written in more than 32 languages: the others are left out |

What cannot be set aside refuses the package with `PackageRefused`: a document that is unsafe or malformed, without a
`<tincan>` root or `<activities>`, an activity without an IRI, more activities than `PackageLimits::$items`, no
activity with a `<launch>`, a launch file that leaves the package, a launch of another scheme — `javascript:`, `data:` —,
or an absolute address that `phil/url` refuses, such as one holding a user name and a password.

## Security

`tincan.xml` is read as the other manifests are: bounded in size, refused when it declares a document type or an
entity, its XML nodes and their depth counted before it is loaded, parsed without network access. Its activities count
against `PackageLimits::$items`. An absolute launch address is read by `phil/url`, as a browser reads it, then kept in
its canonical form.

## What the library leaves to its caller

It does not launch activities, build the launch URL with the endpoint, the actor and the registration, issue
credentials for the Learning Record Store, nor receive or check statements.
