# Equivalent courses

The same training given in several languages is a group of equivalent courses: one course per language, each with its
own content. A learner takes the course of their language, and completing one course of the group completes them all.
A course whose content is translated within one course is another feature, which the bundle does not offer.

## The groups

`Equivalences` writes and reads the groups:

```php
use Phil\CoursesBundle\Application\Equivalences;

$group = $equivalences->create('Fire safety');
$equivalences->add($group->id, $english->id, 'en');
$equivalences->add($group->id, $french->id, 'fr-CA');

$equivalences->equivalents($english->id);   // [$french->id]
$equivalences->remove($french->id);         // the course stays, in no group
$equivalences->dissolve($group->id);        // the courses stay, each on its own
```

A language is a tag of the shape of BCP 47 — a primary language of 2 to 8 letters, then subtags of 1 to 8 letters or
digits —, 35 characters at most, kept in lowercase with `-`: `fr_CA`, `fr-CA` and `FR-ca` name one language.

| Rule | Refusal |
|---|---|
| A course belongs to one group at most | `InvalidCourse`: `The course "…" is already in another group of equivalent courses.` |
| A group holds one course per language | `InvalidCourse`: `The group already holds a course in "fr".` |
| A group holds 50 courses at most | `InvalidCourse` |
| A language is a tag | `InvalidCourse` |
| The group and the course exist | `UnknownRecord` |

Adding a course already in the group changes its language. Removing a course removes it from its group.

## The course of the learner's language

`courseFor(CourseId $course, ?string $language)` gives the course of the group in that language: the course of that
very tag, else the first course of its primary language — `fr-BE` finds `fr`, and `fr` finds `fr-ca` —, else the course
asked for. A course in no group, no language, or a language that is not a tag gives the course asked for.

The outline of a course lists the other courses of its group, each link carrying its language in `hreflang` and
`lang`. The route `phil_courses_language`, `GET /{course}/language` under the prefix of the pages, redirects the
learner to the course of the request's locale; an application links to it wherever it names the course, and sets the
locale of its requests as it does for its pages — from the learner's profile, or from `Accept-Language` with
`framework.set_locale_from_accept_language`.

## A completion recognised across the group

`Completions::completion()` answers for a course the learner's own completion, else the earliest completion of an
equivalent course; its `course` names the course they took. What reads it follows:

| Part | What the group changes |
|---|---|
| [Learning paths](learning-paths.md) | A step is completed by an equivalent course, and opens the steps that wait for it |
| [Mandatory training](mandatory-training.md#equivalent-courses) | An obligation is fulfilled by an equivalent course, completed before or after the assignment |
| [Credits](credits-and-transcripts.md#the-transcript) | A group is credited once a cycle; a course without credits of its own grants those of its group |
| phil/certificates-bundle | A certificate course of one language is awarded on the completion of another |

A completion already recognised is not undone when a course leaves its group or the group is dissolved.
