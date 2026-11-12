# Publication guard

`Workflow\PublicationGuard` is a listener of the guard events of `symfony/workflow`: it holds back a transition — a
publication — while the content of its subject is not accessible. The guard asks the application for that content
through `Contract\SubjectContent`, checks it, and blocks the transition when a finding is the blocking severity or
graver, or when the checker refuses the content.

## The content of a subject

The application knows where the content of a subject is. For a content of `phil/content-bundle`, the subject of
`workflow.phil_content.guard.publish` is the localized draft row, whose body holds the HTML of the draft:

```php
use Phil\AccessibilityCheck\Contract\SubjectContent;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\Ujo\Document;

final class DraftBody implements SubjectContent
{
    public function contentOf(object $subject): string|Document|null
    {
        return $subject instanceof BodyCapable ? $subject->getBody()?->html : null;
    }
}
```

`null` leaves the subject alone: a row without body, a subject of another kind.

## Registering the guard

```yaml
services:
    App\Accessibility\DraftBody: ~

    Phil\AccessibilityCheck\Workflow\PublicationGuard:
        arguments:
            $checker: '@Phil\AccessibilityCheck\AccessibilityChecker'
            $content: '@App\Accessibility\DraftBody'
            $blockAt: !php/enum Phil\AccessibilityCheck\Severity::Error
            $locale: 'fr'
        tags:
            - { name: kernel.event_listener, event: workflow.phil_content.guard.publish }
```

`PublicationGuard::CONTENT_PUBLISH` names that event. Any other guard event — `workflow.<name>.guard.<transition>` —
works the same.

| Argument | Default | Means |
|---|---|---|
| `checker` | | the checker, with the application's policy |
| `content` | | the application's `SubjectContent` |
| `blockAt` | `Severity::Error` | the severity from which a finding blocks; `null` never blocks |
| `locale` | `en` | the locale of the blocker's message |
| `language` | `null` | the language of a content that does not say its own; the policy's when `null` |

## The blocker

A transition held back for its findings carries a `TransitionBlocker` of code `PublicationGuard::BLOCKED`, whose
message — key `report.blocked` — gives the number of blocking findings and the first one, and whose parameters hold the
whole `report`, for the screen that tells the author what to fix:

```php
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\Workflow\PublicationGuard;

foreach ($workflow->buildTransitionBlockerList($row, 'publish') as $blocker) {
    if (PublicationGuard::BLOCKED === $blocker->getCode()) {
        $report = $blocker->getParameters()['report'];   // a Report
    }
}
```

A content the checker refuses blocks with the code `PublicationGuard::REFUSED` and the reason as message. A blocked
guard makes the transition not enabled: with `phil/content-bundle`, `publish()` throws `TransitionRefused`.
