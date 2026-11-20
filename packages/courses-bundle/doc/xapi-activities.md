# xAPI activities

An xAPI package — content that reports to a learning record store with statements, without the rules of cmi5 — runs
as an activity of the type `xapi`. It is launched with the launch parameters xAPI packages read, talks to the same
xAPI endpoint as the cmi5 units, with the same tokens, and moves the learner's progress through its own `completed`,
`passed` and `failed` statements. The learner cannot mark it done.

## The activity

| Setting | Required | What it holds |
|---|---|---|
| `activity_id` | yes | The IRI the package names its activity by — a scheme, a colon, no space, 2 048 bytes at most —, the identifier its statements carry as their object. |
| `url` | one of `url` and `path` | An absolute address of the content. |
| `path`, `query` | one of `url` and `path` | The launch file in the package, served from the content origin, and its query. |
| `launch_method` | no | `AnyWindow`, the default, framed in the activity page; or `OwnWindow`, opened in a window of its own. |
| `mastery_score` | no | The scaled score, from 0 to 1, that passes the activity. |

```php
$courses->addActivity($module->id, 'Knots', 'xapi', [
    'activity_id' => 'https://example.org/xapi/knots',
    'path' => 'knots/index.html',
    'mastery_score' => 0.8,
], CompletionRequirement::Passed);
```

The requirement is the activity's, as for every type: `Completed`, `Passed`, `CompletedAndPassed` or
`CompletedOrPassed`.

## The launch

A launch abandons the session the learner still has open for the activity, opens a new one, and adds to the URL:

| Parameter | Value |
|---|---|
| `endpoint` | The xAPI endpoint of the bundle, the route `phil_courses_xapi`. |
| `auth` | `Basic` and the token of the session. |
| `actor` | The learner as an xAPI agent, named by an account of the site: `{"objectType":"Agent","account":{"homePage":"https://www.example.org/","name":"<learner>"}}`. |
| `registration` | The registration of the enrollment, the same for every launch. |
| `activity_id` | The `activity_id` of the activity. |

The token is the cmi5 one: the session's identifier and an HMAC of it under `phil_courses.cmi5.secret`, so that
nothing secret is kept. xAPI packages read it from the URL rather than from a fetch URL, so the session counts as
fetched at its launch; the token opens that session only, while it is neither abandoned nor expired —
`phil_courses.cmi5.session_ttl` seconds, four hours by default. The home page of the accounts is
`phil_courses.cmi5.actor_home_page`: without it, the launch is refused.

## The statements

The content posts or puts its statements to `statements` of the endpoint, one or a list of up to 50, 1 MiB at most.
Each is checked before the learning record store keeps it:

- the actor is the learner of the session, named by the same account — never another learner, a group or an e-mail
  address;
- the verb is named by an IRI, and is not `voided`: an outcome recorded stays;
- the object is an activity named by an IRI, or a reference to a statement named by a UUID;
- the registration, when the statement names one, is the session's, and is set when it names none;
- the identifier, when given, is a UUID; a scaled score is from -1 to 1.

A statement refused answers `403` with the reason, the statements before it in the request kept; a store that cannot
keep one answers `503`. Statements about other activities of the package — a page, a question — are kept and move
nothing.

A statement about the activity launched moves the learner's progress:

| Verb | Completed | Success |
|---|---|---|
| `http://adlnet.gov/expapi/verbs/completed` | yes | `result.success` when it is a boolean |
| `http://adlnet.gov/expapi/verbs/passed` | when `result.completion` is true | passed |
| `http://adlnet.gov/expapi/verbs/failed` | when `result.completion` is true | failed |

With a `mastery_score`, a scaled score decides success for `completed` and `passed`: at or above it the learner
passed, below it they failed; `failed` stays a failure. The best score is kept, as for every activity.

The state documents of the package — a bookmark, its suspend data — are kept under the activity's `activity_id`, the
registration and their identifier, in the store of the state documents of cmi5. The endpoint serves neither the
statements back nor the learner preferences, as for cmi5.

## The learning record store

The statements go to the `LearningRecordStore` of [cmi5](cmi5.md#the-learning-record-store): the store inside the
bundle, a remote store through phil/safe-http, or the application's own. They are kept under the registration of the
enrollment, with the cmi5 statements of the same learner.

## Importing an xAPI package

An archive that holds `tincan.xml` at its root, and neither `cmi5.xml` nor `imsmanifest.xml`, is an xAPI package. The
import reads it through the `XapiPackageReader` service named in `phil_courses.import.xapi_reader`: an adapter of the
reader of a library, which bounds the reading as phil/elearning-packages does for the other formats. Without one, the
package is refused with a message that says so.

The reader returns an `XapiPackage` — the course's IRI, title and description, its launchable activities and the notes
of the reading —, which the import turns into a course of origin `xapi` with one module named after the course. Each
launchable activity becomes an `xapi` activity that keeps its IRI and its mastery score; one with a mastery score is
satisfied once passed, the others once completed. A launch that is neither a file of the package nor an absolute
address refuses the package. The files of the package are stored and served from the content origin, as for SCORM
and cmi5.

```php
final readonly class LibraryXapiReader implements XapiPackageReader
{
    public function read(PackageArchive $archive): XapiPackage
    {
        // Reads tincan.xml within bounds and returns the launchable activities.
    }
}
```

## Privacy

The launch sessions and the statements of an xAPI activity are those of the enrollment's registration: the privacy
module `courses_cmi5` gives them in an access request and erases them, with the records of cmi5, as
[cmi5](cmi5.md#privacy) explains. A remote store, or the application's, keeps what it was sent; the export says so.
