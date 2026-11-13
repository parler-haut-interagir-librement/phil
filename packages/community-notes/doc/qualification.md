# Qualification

The library writes and rates for qualified members only, and asks the application who they are through the port
`Phil\CommunityNotes\Contract\Qualification`:

```php
interface Qualification
{
    public function mayWrite(ResourceReference $member, ResourceReference $subject): bool;

    public function mayRate(ResourceReference $member, Note $note): bool;
}
```

`mayWrite()` is asked by `write()`, with the resource the note is about; `mayRate()` by `rate()`, with the note. The
author of a note never rates it, whatever the qualification says.

## Through Symfony Security

`AuthorizationQualification` asks Symfony Security, for the signed-in member, who is the one writing or rating. It
needs `symfony/security-core`.

```php
use Phil\CommunityNotes\Security\AuthorizationQualification;

new AuthorizationQualification($authorizationChecker);                                         // two attributes
new AuthorizationQualification($authorizationChecker, 'ROLE_NOTE_WRITER', 'ROLE_NOTE_RATER');  // two roles
```

| Asked by | Attribute, by default | Subject given to the voters |
|---|---|---|
| `mayWrite()` | `COMMUNITY_NOTE_WRITE` | A `QualificationSubject`: the member, the resource, no note |
| `mayRate()` | `COMMUNITY_NOTE_RATE` | A `QualificationSubject`: the member, the resource of the note, the note |

A role is decided by the role hierarchy, as any role. Any other attribute is decided by the application's voters: a
voter that grants `COMMUNITY_NOTE_RATE` to members of six months, or with ten notes of theirs found helpful, reads what
it needs from `QualificationSubject`.

```php
use Phil\CommunityNotes\Security\AuthorizationQualification;
use Phil\CommunityNotes\Security\QualificationSubject;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SeniorRaterVoter extends Voter
{
    public function __construct(
        private readonly Members $members,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return AuthorizationQualification::RATE === $attribute && $subject instanceof QualificationSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->members->joinedMonthsAgo($subject->member) >= 6;
    }
}
```

The checker always decides for the signed-in user: the application passes that user as the member.

## A qualification of the application

Outside Symfony Security, the application implements `Qualification` itself: a record of the member's notes found
helpful, a level of trust, an invitation. It keeps no state between requests.
