# Extension points

The library has three ports, all interfaces of `Phil\Listings\Contract`, which the `Responder` reads. Their adapters
belong to the application.

## `BlockList`

Whether one of two members blocked the other.

```php
interface BlockList
{
    public function isBlocked(MemberId $one, MemberId $other): bool;
}
```

The answer is symmetric: true when either member blocked the other, so that a member who blocks someone neither
receives their responses nor can respond to their listings. The responder asks it before a response and again before
an acceptance, so that a block made in between wins.

## `MemberAges`

The age of a member, in whole years at an instant.

```php
interface MemberAges
{
    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int;
}
```

Null when the application does not know it; a listing with a minimum age then refuses the member with `age_unknown`.
The responder asks it only of a listing with a minimum age, before a response and again before an acceptance. An
adapter computes the age from a birth date it keeps, or answers from a verification it trusts, and never needs to give
the date itself to the library.

```php
use DateTimeImmutable;
use Phil\Listings\Contract\MemberAges;
use Phil\Listings\MemberId;

final readonly class ProfileAges implements MemberAges
{
    public function __construct(
        private Profiles $profiles,
    ) {
    }

    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int
    {
        $birthDate = $this->profiles->birthDateOf($member->value);

        return null === $birthDate ? null : $birthDate->diff($at)->y;
    }
}
```

## `ResponseStore`

Where the responses live.

```php
interface ResponseStore
{
    public function countOpen(ListingId $listing): int;

    public function hasOpen(ListingId $listing, MemberId $respondent): bool;

    public function save(Response $response): void;
}
```

- `countOpen()` counts the pending and accepted responses to a listing — `Response::isOpen()` —, for the kind's bound
  of open responses; an index on the listing and the status keeps it cheap.
- `hasOpen()` tells whether a member has an open response to a listing, so that each member has at most one.
- `save()` writes a response, new or changed; `Response::restore()` rebuilds it from its fields.

Whether a count and the write that follows it are made atomic is the adapter's decision. Without a lock or a unique
constraint on the listing, the respondent and the open statuses, two responses sent at the same instant may both pass
the bounds; a unique index makes the second fail in the store instead.
