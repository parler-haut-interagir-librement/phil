# Extension points

| Point | How |
|---|---|
| Who the member is | Implement `Application\Contract\MemberResolver` and name it in `member_resolver` |
| Who every member is | Implement `Application\Contract\MemberSource` and name it in `member_source` |
| How the members are told | Implement `Application\Contract\TermsNotifier` and name it in `notifier` |
| What is kept of an address | Implement `Application\Contract\AddressMasker` and alias it |
| How the texts are checked, shown and compared | Implement `Application\Contract\PolicyTexts` and alias it |
| Where the versions are kept | Implement `Domain\Contract\PolicyVersionRepository` and alias it |
| Where the acceptances are kept | Implement `Domain\Contract\AcceptanceRepository` and alias it |
| Publishing and accepting from code | `Application\Terms`: `publish()`, `accept()`, `textMatches()` |
| What a member has to accept | `Application\AcceptanceChecker::pending()` |
| Who has not accepted | `Application\Outstanding`: `at()`, `ofLatestMaterial()` |
| Reacting to a publication or an acceptance | Listen to `Application\Event\VersionPublished` and `TermsAccepted` |
| The routes the gate lets through | `gate.exempt_routes`, `gate.exempt_paths` |
| The words of the pages | The messages of the `phil_terms` domain, `policy.<key>` for each title |
| The pages | `pages.layout`, `admin.layout`, and the templates under `@PhilTerms/` overridden in `templates/bundles/PhilTermsBundle/` |
| The look of the pages | The recipes of the kit the application installs — PHIL UX or Shadcn —, edited in its own components directory; `kit_subdirectory` where they are |
| The clock | The `clock` option: the bundle never reads the time itself |

## The invariants of a store

An `AcceptanceRepository`:

- adds an acceptance, never changes one, and removes them only in `erase()`;
- answers `compliant()` for at most 500 members at once, from the acceptances of a version numbered at least the one
  asked, at or after the moment given.

A `PolicyVersionRepository` refuses a second version of the same number with `InvalidTerms` of reason `version_taken`,
and gives the versions of a policy the newest first.

## A notifier of its own

```php
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

final readonly class MailingListNotifier implements TermsNotifier
{
    public function published(PolicyVersion $version): void { /* announce it to the list */ }

    public function remind(string $policy, int $version, array $members): void { /* 500 members at most */ }
}
```
