# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `mbstring` | The text of a note counted in characters. |
| `phil/resource` | `^0.1` | Subjects, authors and raters named as references (`post:42`, `user:7`). |
| `symfony/uid` | `^8.2` | The random identifiers of the notes. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The time of a note and of a rating; the events. |
| `symfony/security-core` | `^8.2`, suggested | `AuthorizationQualification`, which asks Symfony Security who may write and rate. |

```sh
composer require phil/community-notes
```

## The services

`CommunityNotes` takes the stores of notes and ratings, the qualification, a clock, the rule — `new
CrossAgreementRule()` by default — and, optionally, an event dispatcher. In a Symfony application:

```yaml
# config/services.yaml
services:
    Phil\CommunityNotes\Contract\NoteRepository: '@App\CommunityNotes\DoctrineNoteRepository'
    Phil\CommunityNotes\Contract\RatingRepository: '@App\CommunityNotes\DoctrineRatingRepository'

    Phil\CommunityNotes\Security\AuthorizationQualification:
        arguments: ['@security.authorization_checker', 'ROLE_NOTE_WRITER', 'ROLE_NOTE_RATER']
    Phil\CommunityNotes\Contract\Qualification: '@Phil\CommunityNotes\Security\AuthorizationQualification'

    Phil\CommunityNotes\CrossAgreementRule:
        arguments: { $minRatings: 5, $pairs: 2 }

    Phil\CommunityNotes\CommunityNotes:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'

    Phil\CommunityNotes\Message\ReconsiderNoteHandler:
        tags: [{ name: messenger.message_handler }]
```

The stores of the application implement `NoteRepository` and `RatingRepository`: see
[Extension points](extension-points.md). The stores in memory, `InMemoryNoteRepository` and
`InMemoryRatingRepository`, serve tests and demonstrations.
